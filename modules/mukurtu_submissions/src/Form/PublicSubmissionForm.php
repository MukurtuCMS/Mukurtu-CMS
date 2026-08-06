<?php

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Drupal\mukurtu_submissions\Plugin\Field\FieldWidget\SimpleMediaUploadWidget;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public, bundle-agnostic content submission form.
 *
 * Renders whichever fields the target bundle's "submission" entity form
 * display exposes, always excluding protocol/group-membership fields - the
 * acting user (often anonymous) never has standing to set those, and
 * CulturalProtocolItem::preSave() strips any value that slips through
 * regardless, so this exclusion is defense-in-depth, not the only guard.
 */
class PublicSubmissionForm extends FormBase {

  /**
   * Fields never exposed on the public submission form, regardless of how
   * the target bundle's "submission" form display is configured. Includes
   * both protocol/group-membership fields a submitter should never set,
   * and administrative base fields (authoring info, revision log, URL
   * alias, moderation state) that EntityDisplayBase::init() re-adds with
   * their own default widget whenever a form mode leaves them unmentioned
   * in "content"/"hidden" - most of those default widgets are themselves
   * gated by field-level access checks that hide them from a truly
   * unprivileged anonymous visitor, but not from an authenticated
   * submitter or reviewer previewing the form, so they must be stripped
   * unconditionally here rather than relying on per-user field access.
   */
  const EXCLUDED_FIELDS = [
    'field_cultural_protocols',
    'og_audience',
    'uid',
    'created',
    'status',
    'revision_log',
    'path',
    'moderation_state',
    'langcode',
    'url_redirects',
    // Computed/display-only fields - no meaningful editable widget exists
    // for these regardless of bundle, so they should never be offered as
    // an includable option.
    'field_representative_media',
    'field_citation',
    'field_multipage_page_of',
    'field_all_related_content',
    'field_in_collection',
    // Real, editable fields excluded by policy: content type is implied by
    // the submission settings itself, and the original record link isn't
    // something a submitter should be setting directly.
    'field_content_type',
    'field_mukurtu_original_record',
  ];

  /**
   * The entity being built by this form.
   */
  protected ?ContentEntityInterface $entity = NULL;

  /**
   * The "submission" mode form display for the target bundle.
   */
  protected ?EntityFormDisplayInterface $display = NULL;

  /**
   * The submission settings entity for the target bundle.
   */
  protected ?SubmissionSettingsInterface $settings = NULL;

  /**
   * Group ID => parent group ID, populated by groupFields(); used to walk a
   * field's full ancestor chain when a validation error needs to force a
   * nested group open (see validateForm()).
   */
  protected array $groupParents = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected ConfigFactoryInterface $submissionsConfigFactory,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('config.factory'),
      $container->get('account_switcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_submissions_public_submission_form';
  }

  /**
   * Title callback for the submission route.
   */
  public static function title($entity_type_id, $bundle) {
    $settings_storage = \Drupal::entityTypeManager()->getStorage('mukurtu_submission_settings');
    $matches = $settings_storage->loadByProperties([
      'target_entity_type_id' => $entity_type_id,
      'target_bundle' => $bundle,
    ]);
    if ($settings = reset($matches)) {
      return $settings->label();
    }

    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
    $label = $bundle_info[$bundle]['label'] ?? $bundle;
    return t('Submit a @type', ['@type' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $bundle = NULL) {
    if (!$this->entity) {
      $settings_storage = $this->entityTypeManager->getStorage('mukurtu_submission_settings');
      $matches = $settings_storage->loadByProperties([
        'target_entity_type_id' => $entity_type_id,
        'target_bundle' => $bundle,
      ]);
      $this->settings = reset($matches) ?: NULL;

      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundle_key = $entity_type->getKey('bundle') ?: 'type';
      $this->entity = $this->entityTypeManager->getStorage($entity_type_id)->create([$bundle_key => $bundle]);
      $this->setPendingSubmissionState($this->entity);

      $this->display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
      foreach (self::EXCLUDED_FIELDS as $excluded_field) {
        $this->display->removeComponent($excluded_field);
      }
      $this->restrictMediaTypes();
    }

    $form['#entity_type_id'] = $entity_type_id;
    $form['#bundle'] = $bundle;

    $this->display->buildForm($this->entity, $form, $form_state);
    $this->labelRemoveButtons($form);
    $this->groupFields($form);

    // Weighted below Title's own weight (-10) in the "submission" form
    // display, so admin-configured intro text always renders directly above
    // Title regardless of what other fields a bundle exposes.
    $intro_text = $this->settings?->getIntroText() ?? [];
    if (!empty($intro_text['value'])) {
      $form['intro_text'] = [
        '#type' => 'processed_text',
        '#text' => $intro_text['value'],
        '#format' => $intro_text['format'] ?? NULL,
        '#weight' => -11,
      ];
    }

    $form['required_fields_legend'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Fields marked with * are required.'),
      '#attributes' => ['class' => ['form-required-legend']],
      '#weight' => -10.5,
    ];

    $form['#attached']['library'][] = 'mukurtu_submissions/character_counter';
    $form['#attached']['library'][] = 'mukurtu_submissions/flatten_tables';

    $account = $this->currentUser();
    $form['submitter_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Your contact information'),
      // Not a collapsible group (Name/Email are required - nothing to
      // gain by hiding them), but given the same "submission-section"
      // heading treatment as the field groups per feedback that it
      // shouldn't look visually lighter-weight than them.
      '#attributes' => ['class' => ['submission-section']],
      // Weighted just after the intro text (-11) and required-fields
      // legend (-10.5), but before Title (-10) - at the very top of the
      // form's own content, right below any admin-configured framing text.
      '#weight' => -10.2,
    ];
    $form['submitter_info']['submitter_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#required' => TRUE,
      '#default_value' => $account->isAuthenticated() ? $account->getDisplayName() : '',
      '#attributes' => ['autocomplete' => 'name'],
    ];
    $form['submitter_info']['submitter_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your email'),
      '#required' => TRUE,
      '#default_value' => $account->isAuthenticated() ? $account->getEmail() : '',
      '#attributes' => ['autocomplete' => 'email'],
    ];
    if ($this->settings && $this->settings->accessExpectationsEnabled()) {
      $form['submitter_info']['access_expectations'] = [
        '#type' => 'textarea',
        '#title' => $this->t('How do you expect this item to be shared?'),
        '#description' => $this->t('Optional. Describe any access restrictions you expect (e.g. "only for my community", "no restrictions") to help the reviewer choose an appropriate sharing setting.'),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 200,
    ];

    return $form;
  }

  /**
   * Restricts the media_library_widget's allowed types to the settings entity's choice.
   */
  protected function restrictMediaTypes(): void {
    if (!$this->settings) {
      return;
    }
    $allowed = $this->settings->getAllowedMediaTypes();
    if (empty($allowed)) {
      return;
    }

    // Not hardcoded to field_media_assets - applies to any media-reference
    // field the bundle exposes, whichever widget it ended up with (the
    // Media Library picker, or our own SimpleMediaUploadWidget).
    foreach ($this->display->getComponents() as $field_name => $component) {
      switch ($component['type'] ?? NULL) {
        case 'media_library_widget':
          $current_types = $component['settings']['media_types'] ?? [];
          $component['settings']['media_types'] = array_values(array_intersect($current_types, $allowed));
          $this->display->setComponent($field_name, $component);
          break;

        case 'mukurtu_simple_media_upload':
          $current_bundles = $component['settings']['allowed_bundles'] ?? [];
          $component['settings']['allowed_bundles'] = array_values(array_intersect($current_bundles, $allowed));
          $this->display->setComponent($field_name, $component);
          break;
      }
    }
  }

  /**
   * Wraps admin-assigned fields into collapsible <details> sections
   * (SubmissionSettings::getFieldGroups()/getFieldGroupAssignments()), in
   * display order, with groups themselves nestable inside a parent group
   * (SubmissionSettings::getFieldGroups()'s "parent" key) - mirroring the
   * internal admin form's nested field_group tabs (e.g. "Local Contexts"
   * inside "Permissions and Rights"). A field with no group assignment is
   * left exactly where $this->display->buildForm() put it - grouping is
   * purely additive.
   *
   * Each group's own #weight is the lowest #weight found anywhere in its
   * subtree (its own direct fields, or - recursively - its child groups'
   * fields), so it renders at the position its first member would have
   * occupied - keeping grouped and ungrouped fields correctly interleaved
   * in whatever order the settings form configured. A group with nothing
   * in its subtree (e.g. every field it would have contained was excluded)
   * is skipped rather than rendered empty.
   */
  protected function groupFields(array &$form): void {
    $assignments = $this->settings?->getFieldGroupAssignments() ?? [];
    $group_defs = [];
    foreach ($this->settings?->getFieldGroups() ?? [] as $group) {
      $group_defs[$group['id']] = $group + ['parent' => ''];
    }
    if (!$assignments || !$group_defs) {
      return;
    }
    foreach ($group_defs as $group_id => $group) {
      $this->groupParents[$group_id] = $group['parent'];
    }

    $children_of = [];
    foreach ($group_defs as $group_id => $group) {
      if ($group['parent'] !== '' && isset($group_defs[$group['parent']])) {
        $children_of[$group['parent']][] = $group_id;
      }
    }

    $direct_weights = [];
    foreach ($assignments as $field_name => $group_id) {
      if (!isset($form[$field_name]) || !isset($group_defs[$group_id])) {
        continue;
      }
      $weight = $form[$field_name]['#weight'] ?? 0;
      if (!isset($direct_weights[$group_id]) || $weight < $direct_weights[$group_id]) {
        $direct_weights[$group_id] = $weight;
      }
    }

    // Resolve each group's effective weight (own direct fields, or the
    // lowest effective weight among its children), memoized, with a
    // visited-set guard against a cyclical parent chain slipping through
    // (SubmissionSettingsForm::breakGroupParentCycles() should already
    // prevent this at save time - this is defense in depth only).
    $effective_weights = [];
    $resolve_weight = function (string $group_id, array &$visiting) use (&$resolve_weight, &$effective_weights, $direct_weights, $children_of): ?int {
      if (array_key_exists($group_id, $effective_weights)) {
        return $effective_weights[$group_id];
      }
      if (isset($visiting[$group_id])) {
        return NULL;
      }
      $visiting[$group_id] = TRUE;

      $weight = $direct_weights[$group_id] ?? NULL;
      foreach ($children_of[$group_id] ?? [] as $child_id) {
        $child_weight = $resolve_weight($child_id, $visiting);
        if ($child_weight !== NULL && ($weight === NULL || $child_weight < $weight)) {
          $weight = $child_weight;
        }
      }

      unset($visiting[$group_id]);
      return $effective_weights[$group_id] = $weight;
    };
    foreach (array_keys($group_defs) as $group_id) {
      $visiting = [];
      $resolve_weight($group_id, $visiting);
    }

    foreach ($group_defs as $group_id => $group) {
      if ($effective_weights[$group_id] === NULL) {
        // Nothing anywhere in this group's subtree - skip it entirely
        // rather than render an empty section.
        continue;
      }
      $form[$group_id] = [
        '#type' => 'details',
        '#title' => $group['label'],
        '#open' => empty($group['collapsed']),
        '#weight' => $effective_weights[$group_id],
      ];
    }

    foreach ($assignments as $field_name => $group_id) {
      if (!isset($form[$field_name]) || !isset($form[$group_id])) {
        continue;
      }
      $form[$group_id][$field_name] = $form[$field_name];
      unset($form[$field_name]);
    }

    // Nest child groups into their parent's already-populated container.
    // Repeated passes (rather than a single walk) handle arbitrary nesting
    // depth regardless of $group_defs' iteration order.
    $moved = TRUE;
    while ($moved) {
      $moved = FALSE;
      foreach ($group_defs as $group_id => $group) {
        $parent_id = $group['parent'];
        if ($parent_id !== '' && isset($form[$group_id]) && isset($form[$parent_id])) {
          $form[$parent_id][$group_id] = $form[$group_id];
          unset($form[$group_id]);
          $moved = TRUE;
        }
      }
    }
  }

  /**
   * Gives every multi-value/paragraph field's "Remove" button a distinct
   * accessible name (e.g. "Remove Related Content item 2") instead of the
   * bare, indistinguishable "Remove" text such widgets render by default.
   * Harmless for an editor who can see the surrounding layout, but
   * ambiguous for a screen reader user hearing "Remove", "Remove" with no
   * indication of which field or item each button belongs to - and this
   * form's audience skews toward first-time, untrained visitors more than
   * the admin content forms these widgets were originally built for.
   */
  protected function labelRemoveButtons(array &$element, ?string $field_label = NULL, ?int $delta = NULL): void {
    foreach (Element::children($element) as $key) {
      $child_delta = is_int($key) ? $key : $delta;
      $child_label = $field_label;
      if ($child_label === NULL && is_string($key) && $this->entity->hasField($key)) {
        $child_label = (string) $this->entity->get($key)->getFieldDefinition()->getLabel();
      }

      if ($child_label !== NULL
        && ($element[$key]['#type'] ?? NULL) === 'submit'
        && isset($element[$key]['#value'])
        && (string) $element[$key]['#value'] === (string) $this->t('Remove')
        && !isset($element[$key]['#attributes']['aria-label'])
      ) {
        $element[$key]['#attributes']['aria-label'] = $child_delta !== NULL
          ? $this->t('Remove @field item @number', ['@field' => $child_label, '@number' => $child_delta + 1])
          : $this->t('Remove @field', ['@field' => $child_label]);
      }

      $this->labelRemoveButtons($element[$key], $child_label, $child_delta);
    }
  }

  /**
   * Sets the entity into the "pending public submission" state: owned by
   * the service account, unpublished, and (if moderated) in the workflow's
   * "draft" state - applied at creation time so it's already in place
   * before content_moderation's transition constraint runs in validateForm().
   */
  protected function setPendingSubmissionState(ContentEntityInterface $entity): void {
    $service_account_uid = (int) $this->submissionsConfigFactory->get('mukurtu_submissions.settings')->get('service_account_uid');
    if ($entity instanceof EntityOwnerInterface && $service_account_uid) {
      $entity->setOwnerId($service_account_uid);
    }
    if ($entity instanceof NodeInterface) {
      $entity->setUnpublished();
      if ($entity->hasField('moderation_state')) {
        $entity->set('moderation_state', 'draft');
      }
    }
    if ($entity instanceof CulturalProtocolControlledInterface) {
      // Explicitly empty rather than left NULL. The field is still reported
      // as "required but empty" by validate() - filtered out in
      // validateForm(), see the comment there - because a submission is
      // deliberately created with no protocol until a reviewer assigns one.
      $entity->setProtocols([]);
    }
  }

  /**
   * Creates real Media entities from any SimpleMediaUploadWidget field's
   * raw uploaded files, and sets them onto $this->entity - called from
   * submitForm(), never from the field's own massageFormValues(), since
   * that runs on every validateForm() pass (including the intermediate
   * "Upload" button click), not just the final submission - see
   * SimpleMediaUploadWidget's class docblock for why creating the real,
   * unpublished media that early breaks the anonymous visitor's own
   * access to their just-uploaded file on the next request.
   */
  protected function createUploadedMedia(FormStateInterface $form_state): void {
    $service_account_uid = (int) $this->submissionsConfigFactory->get('mukurtu_submissions.settings')->get('service_account_uid');
    $owner_uid = $service_account_uid ?: 1;

    foreach ($this->display->getComponents() as $field_name => $component) {
      if (($component['type'] ?? NULL) !== 'mukurtu_simple_media_upload') {
        continue;
      }
      $widget = $this->display->getRenderer($field_name);
      if (!$widget instanceof SimpleMediaUploadWidget) {
        continue;
      }
      $fids = $form_state->getValue([$field_name, 'upload'], []);
      $this->entity->set($field_name, $widget->createMediaFromUpload($fids, $owner_uid));
    }
  }

  /**
   * Runs a callback as the superuser, bypassing per-user permission checks
   * (e.g. content_moderation's transition-permission validation) that don't
   * apply to a visitor's submission being placed into moderation on their
   * behalf rather than authored interactively. Mirrors the account-switch
   * pattern mukurtu_import's migrate destination plugins use for the same
   * kind of programmatic, non-interactive entity save.
   */
  protected function asSuperuser(callable $callback) {
    $this->accountSwitcher->switchTo($this->entityTypeManager->getStorage('user')->load(1));
    try {
      return $callback();
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Re-assert pending-submission state here (not just at entity creation
    // in buildForm()): Drupal's form-processing pipeline (#process/#after_build
    // callbacks running between buildForm() returning and validateForm()
    // being invoked) resets a moderated entity's moderation_state/status back
    // to the workflow's default published state before this point, for
    // reasons internal to content_moderation/Field API rather than anything
    // this form does - re-applying here is cheap and idempotent.
    $this->setPendingSubmissionState($this->entity);
    $this->display->extractFormValues($this->entity, $form, $form_state);
    $violations = $this->asSuperuser(fn () => $this->entity->validate());
    foreach ($violations as $violation) {
      $property_path = $violation->getPropertyPath();
      // field_cultural_protocols is required at the field level, but a
      // submission is deliberately created with none - CulturalProtocolItem
      // treats an empty protocol set as "required but empty" regardless of
      // context. CulturalProtocolItem::setValue() has its own precedent for
      // this exact tradeoff (an "invalid" empty protocol value used to lock
      // a node down to its owner/superuser only), so this form accepts the
      // same tradeoff rather than requiring a fabricated placeholder
      // protocol just to satisfy validation.
      if ($property_path === 'field_cultural_protocols') {
        continue;
      }
      // Map the violation back to its actual widget (e.g. "field_foo.0.value"
      // -> "field_foo") so Drupal highlights/focuses the right element and
      // wires up aria-describedby, instead of dumping every error at the top
      // of the form with no indication of which field it belongs to.
      $field_name = explode('.', $property_path)[0];
      $form_state->setErrorByName($field_name, $violation->getMessage());

      // A required field's error must never be left hidden behind a
      // collapsed group - force that group, and every ancestor it's
      // nested inside, open on redisplay regardless of their configured
      // default state.
      $group_id = $this->settings?->getFieldGroupAssignments()[$field_name] ?? NULL;
      if ($group_id !== NULL) {
        $this->openGroupChain($form, $group_id);
      }
    }
  }

  /**
   * Forces a group, and every ancestor it's nested inside (per
   * $this->groupParents, populated by groupFields()), open - so a group
   * buried several levels deep in nesting still surfaces a validation
   * error even if every ancestor defaults to collapsed.
   */
  protected function openGroupChain(array &$form, string $group_id): void {
    $chain = [$group_id];
    $current = $group_id;
    while (!empty($this->groupParents[$current])) {
      $current = $this->groupParents[$current];
      $chain[] = $current;
    }

    $ref = &$form;
    foreach (array_reverse($chain) as $id) {
      if (!isset($ref[$id])) {
        return;
      }
      $ref[$id]['#open'] = TRUE;
      $ref = &$ref[$id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->setPendingSubmissionState($this->entity);
    $this->createUploadedMedia($form_state);
    $this->asSuperuser(function () {
      $this->entity->save();
    });

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->create([
      'target_entity_type' => $this->entity->getEntityTypeId(),
      'target_id' => $this->entity->id(),
      'submitter_name' => $form_state->getValue('submitter_name'),
      'submitter_email' => $form_state->getValue('submitter_email'),
      'access_expectations' => $form_state->getValue('access_expectations'),
      'submitter_ip' => \Drupal::request()->getClientIp(),
    ]);
    $submission->save();

    $form_state->setRedirect('mukurtu_submissions.thank_you', [
      'entity_type_id' => $form['#entity_type_id'],
      'bundle' => $form['#bundle'],
    ]);
  }

}

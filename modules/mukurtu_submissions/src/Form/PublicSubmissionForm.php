<?php

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Drupal\mukurtu_submissions\Plugin\Field\FieldWidget\SimpleMediaUploadWidget;
use Drupal\mukurtu_submissions\SessionCreatedEntities;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

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
    'promote',
    'sticky',
    'comment',
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
    // A first-time visitor has nothing on the site yet to relate their
    // submission to - cross-referencing other content is a curation step
    // for after a reviewer publishes it, not something to ask for here.
    'field_related_content',
    // Sub-collections are built by adding existing collections as children
    // from the parent collection's own edit form - not something a first
    // submission (which doesn't exist as a real node yet) can meaningfully
    // reference either.
    'field_child_collections',
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
    protected EntityFieldManagerInterface $entityFieldManager,
    protected SessionCreatedEntities $sessionCreatedEntities,
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
      $container->get('entity_field.manager'),
      $container->get('mukurtu_submissions.session_created_entities'),
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
    $this->removeDragDropButtons($form);
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

    $this->restrictMediaTypesOnDisplay($this->display, $this->entity->getEntityTypeId(), $this->entity->bundle(), $allowed, []);
  }

  /**
   * Applies restrictMediaTypes()'s restriction to $display, and recurses
   * into any paragraph-referencing field's own provisioned "submission"
   * display, so a paragraph-nested media field (e.g. dictionary_word's
   * sample-sentence recording) gets the same restriction a field living
   * directly on the bundle does.
   *
   * $display here is always the exact object instance later passed to
   * EntityFormDisplay::collectRenderDisplay() deeper inside the paragraphs
   * widget (both fetched via entity_display.repository/entity_type.manager
   * for the same "$entity_type.$bundle.submission" ID, which Drupal's
   * per-request entity static cache returns as the same PHP object) - so
   * mutating it in place here, without saving, is enough for the
   * restriction to actually apply when the widget builds that item's
   * subform, exactly as it already does for $this->display itself.
   *
   * Not hardcoded to field_media_assets - applies to any media-reference
   * field the bundle exposes, whichever widget it ended up with (the
   * Media Library picker, or our own SimpleMediaUploadWidget).
   */
  protected function restrictMediaTypesOnDisplay(EntityFormDisplayInterface $display, string $entity_type_id, string $bundle, array $allowed, array $visited): void {
    $visited_key = $entity_type_id . ':' . $bundle;
    if (isset($visited[$visited_key])) {
      return;
    }
    $visited[$visited_key] = TRUE;

    foreach ($display->getComponents() as $field_name => $component) {
      switch ($component['type'] ?? NULL) {
        case 'media_library_widget':
          $current_types = $component['settings']['media_types'] ?? [];
          $component['settings']['media_types'] = array_values(array_intersect($current_types, $allowed));
          $display->setComponent($field_name, $component);
          break;

        case 'mukurtu_simple_media_upload':
          $current_bundles = $component['settings']['allowed_bundles'] ?? [];
          $component['settings']['allowed_bundles'] = array_values(array_intersect($current_bundles, $allowed));
          $display->setComponent($field_name, $component);
          break;

        case 'paragraphs':
        case 'entity_reference_paragraphs':
          $definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name] ?? NULL;
          if (!$definition) {
            break;
          }
          $target_bundles = array_keys(array_filter($definition->getSetting('handler_settings')['target_bundles'] ?? []));
          foreach ($target_bundles as $paragraph_bundle) {
            $paragraph_display = $this->entityDisplayRepository->getFormDisplay('paragraph', $paragraph_bundle, 'submission');
            $this->restrictMediaTypesOnDisplay($paragraph_display, 'paragraph', $paragraph_bundle, $allowed, $visited);
          }
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
   *
   * $entity_type_id/$bundle track whose field definitions a label is
   * resolved against - the top-level entity's own, until recursion steps
   * past a paragraph item's "subform" (both "paragraphs" and "entity_
   * reference_paragraphs" widgets set "#paragraph_type" on that item's own
   * container), at which point $field_label is reset and every field name
   * below is resolved against that paragraph's own bundle instead. Without
   * this, a field nested two-or-more paragraph-levels deep (e.g.
   * dictionary_word's sample-sentence recording, itself nested inside a
   * "Word Entries" paragraph) incorrectly inherited its outermost
   * ancestor's label on every Remove button below it.
   */
  protected function labelRemoveButtons(array &$element, ?string $entity_type_id = NULL, ?string $bundle = NULL, ?string $field_label = NULL, ?int $delta = NULL): void {
    $entity_type_id ??= $this->entity->getEntityTypeId();
    $bundle ??= $this->entity->bundle();

    foreach (Element::children($element) as $key) {
      $child_delta = is_int($key) ? $key : $delta;
      $child_label = $field_label;
      if (is_string($key)) {
        $definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$key] ?? NULL;
        if ($definition) {
          $child_label = (string) $definition->getLabel();
        }
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

      if ($key === 'subform' && isset($element['#paragraph_type'])) {
        $this->labelRemoveButtons($element[$key], 'paragraph', $element['#paragraph_type'], NULL, NULL);
        continue;
      }

      $this->labelRemoveButtons($element[$key], $entity_type_id, $bundle, $child_label, $child_delta);
    }
  }

  /**
   * Strips the paragraphs widget's "Drag & drop" bulk-reorder button
   * (ParagraphsWidget::buildHeaderActions(), always rendered at
   * $element['header_actions']['dropdown_actions']['dragdrop_mode'] once
   * there's at least one item, regardless of any widget setting - there's
   * no per-field way to disable it upstream) from every paragraph field on
   * this form. A visitor filling out a one-time submission has at most a
   * handful of items in any repeating field here; reordering tooling built
   * for content editors managing many rows is unneeded complexity for
   * them, on every paragraph field this form has, not just one.
   */
  protected function removeDragDropButtons(array &$element): void {
    foreach (Element::children($element) as $key) {
      if ($key === 'header_actions' && isset($element[$key]['dropdown_actions']['dragdrop_mode'])) {
        unset($element[$key]['dropdown_actions']['dragdrop_mode']);
      }
      $this->removeDragDropButtons($element[$key]);
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

    $this->createUploadedMediaOnEntity($this->entity, $this->display, [], $form_state, $owner_uid);
  }

  /**
   * Does the actual work of createUploadedMedia() for $entity/$display,
   * and recurses into any paragraph-referencing field's own already-
   * extracted (unsaved, in-memory) items - by the time submitForm() runs,
   * validateForm() has already called $this->display->extractFormValues(),
   * which creates the referenced Paragraph entities and attaches them to
   * $this->entity, but SimpleMediaUploadWidget::massageFormValues() is
   * deliberately a no-op (see createMediaFromUpload()'s own docblock), so
   * a paragraph-nested upload field (e.g. dictionary_word's sample-
   * sentence recording) needs this same raw-fid-to-real-media conversion
   * applied to it directly, exactly as the top-level entity does.
   *
   * $form_state_parents is the path SimpleMediaUploadWidget's raw "upload"
   * value lives under in this request's submitted $form_state - [] for
   * $this->entity itself. Both paragraph widgets this profile uses
   * ("paragraphs" and "entity_reference_paragraphs") build each item's
   * inline subform under "$field_name/$delta/subform" (confirmed against
   * InlineParagraphsWidget/ParagraphsWidget::formElement()), so this
   * appends that same three-segment path per level of nesting - correct
   * for arbitrary paragraph-in-paragraph depth, not just one level.
   */
  protected function createUploadedMediaOnEntity(ContentEntityInterface $entity, EntityFormDisplayInterface $display, array $form_state_parents, FormStateInterface $form_state, int $owner_uid): void {
    foreach ($display->getComponents() as $field_name => $component) {
      $type = $component['type'] ?? NULL;

      if ($type === 'mukurtu_simple_media_upload') {
        $widget = $display->getRenderer($field_name);
        if (!$widget instanceof SimpleMediaUploadWidget) {
          continue;
        }
        $fids = $form_state->getValue([...$form_state_parents, $field_name, 'upload'], []);
        $entity->set($field_name, $widget->createMediaFromUpload($fids, $owner_uid));
        continue;
      }

      if (($type === 'paragraphs' || $type === 'entity_reference_paragraphs') && $entity->hasField($field_name)) {
        foreach ($entity->get($field_name) as $delta => $item) {
          $paragraph = $item->entity ?? NULL;
          if (!$paragraph instanceof ContentEntityInterface) {
            continue;
          }
          $paragraph_display = $this->entityDisplayRepository->getFormDisplay($paragraph->getEntityTypeId(), $paragraph->bundle(), 'submission');
          $this->createUploadedMediaOnEntity($paragraph, $paragraph_display, [...$form_state_parents, $field_name, $delta, 'subform'], $form_state, $owner_uid);
        }
      }
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

    // Paragraph widgets (ParagraphsWidget/InlineParagraphsWidget) validate
    // their own paragraph entity's fields as part of their own
    // extractFormValues() - calling $display->validateFormValues() and
    // flagging errors onto $form_state directly - rather than surfacing as
    // violations on $this->entity->validate() below, which only ever sees
    // the TOP-LEVEL entity's own fields. A paragraph-nested reference to a
    // session-created entity (e.g. dictionary_word's "related person" link)
    // needs its own pass over $form_state's errors for exactly that reason.
    $this->suppressSessionCreatedEntityErrors($form_state);

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
      // A reference to an entity this same session legitimately just
      // created via a "quick create" flow (e.g. mukurtu_person's inline
      // "Create a new person record" link) - the entity is real, but isn't
      // independently viewable yet (a fresh submission has no cultural
      // protocol assigned, so node_access denies everyone but its owner),
      // which core's own reference validation otherwise flags as invalid.
      // See SessionCreatedEntities for why this is safe to trust. Covers
      // any TOP-LEVEL entity-reference field with this same issue - the
      // paragraph-nested case is handled above, before this loop even runs.
      if ($this->isReferenceToSessionCreatedEntity($violation)) {
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
   * Whether $violation is a ValidReferenceConstraint rejection (core's
   * own entity-reference validation, which independently re-checks the
   * current user's view access on whatever target_id was submitted,
   * regardless of how it got into the form) whose target matches an
   * entity SessionCreatedEntities recorded this same session as having
   * legitimately just created.
   *
   * Reads the target type/id from the violation's own message parameters
   * (set explicitly by ValidReferenceConstraintValidator via setParameter()
   * as '%type'/'%id') rather than trying to resolve them from the
   * violation's property path - the path for a paragraph-nested field
   * (e.g. field_related_people.0.entity.field_related_person.0.target_id)
   * doesn't cleanly reduce to "the first segment" the way a flat top-level
   * field's does, and the parameters are already exactly the values that
   * were checked, unambiguously, regardless of nesting depth.
   */
  protected function isReferenceToSessionCreatedEntity(ConstraintViolationInterface $violation): bool {
    if (!$violation->getConstraint() instanceof ValidReferenceConstraint) {
      return FALSE;
    }
    $parameters = $violation->getParameters();
    $target_type = $parameters['%type'] ?? NULL;
    $target_id = $parameters['%id'] ?? NULL;
    if (!$target_type || $target_id === NULL || $target_id === '') {
      return FALSE;
    }
    return $this->sessionCreatedEntities->wasCreatedThisSession((string) $target_type, (string) $target_id);
  }

  /**
   * Removes any $form_state error matching a ValidReferenceConstraint
   * rejection whose target matches an entity SessionCreatedEntities
   * recorded this session as having legitimately just created - the
   * paragraph-widget-level equivalent of isReferenceToSessionCreatedEntity(),
   * needed because those errors are flagged directly onto $form_state (via
   * EntityFormDisplay::validateFormValues()/flagWidgetsErrorsFromViolations(),
   * called from within the paragraph widget's own extractFormValues()) and
   * never appear as violations on $this->entity->validate().
   *
   * Matches by message text, built from the constraint's own raw message
   * template rather than a hardcoded string, since $form_state->getErrors()
   * only ever exposes the final rendered message, not the structured
   * violation/parameters isReferenceToSessionCreatedEntity() reads - stays
   * correct if a site (or a future core version) translates or otherwise
   * customizes that template. Drupal's %-placeholder rendering
   * (FormattableMarkup) wraps each substitution in
   * '<em class="placeholder">...</em>', which the pattern accounts for.
   */
  protected function suppressSessionCreatedEntityErrors(FormStateInterface $form_state): void {
    $errors = $form_state->getErrors();
    if (!$errors) {
      return;
    }

    // '#', not '/', as the delimiter - the replacement text below contains
    // literal '/' (in "</em>"), which would otherwise be misread as an
    // early close of the pattern.
    $pattern = preg_quote((new ValidReferenceConstraint())->message, '#');
    $pattern = str_replace(
      ['%type', '%id'],
      ['<em class="placeholder">(?<type>[^<]+)</em>', '<em class="placeholder">(?<id>[^<]+)</em>'],
      $pattern
    );
    $pattern = '#^' . $pattern . '$#';

    $remaining = [];
    $changed = FALSE;
    foreach ($errors as $name => $message) {
      if (preg_match($pattern, (string) $message, $matches)
        && $this->sessionCreatedEntities->wasCreatedThisSession($matches['type'], $matches['id'])) {
        $changed = TRUE;
        continue;
      }
      $remaining[$name] = $message;
    }

    if ($changed) {
      $form_state->clearErrors();
      foreach ($remaining as $name => $message) {
        $form_state->setErrorByName((string) $name, $message);
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

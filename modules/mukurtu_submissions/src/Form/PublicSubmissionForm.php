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
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
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
   * the target bundle's "submission" form display is configured.
   */
  const EXCLUDED_FIELDS = ['field_cultural_protocols', 'og_audience'];

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

    $account = $this->currentUser();
    $form['submitter_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Your contact information'),
      '#weight' => 100,
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
    $form['submitter_info']['submitter_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Your phone number'),
      '#attributes' => ['autocomplete' => 'tel'],
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
    $component = $this->display->getComponent('field_media_assets');
    if (!$component || ($component['type'] ?? NULL) !== 'media_library_widget') {
      return;
    }
    $allowed = $this->settings->getAllowedMediaTypes();
    if (empty($allowed)) {
      return;
    }
    $current_types = $component['settings']['media_types'] ?? [];
    $component['settings']['media_types'] = array_values(array_intersect($current_types, $allowed));
    $this->display->setComponent('field_media_assets', $component);
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
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->setPendingSubmissionState($this->entity);
    $this->asSuperuser(function () {
      $this->entity->save();
    });

    $submission = $this->entityTypeManager->getStorage('mukurtu_submission')->create([
      'target_entity_type' => $this->entity->getEntityTypeId(),
      'target_id' => $this->entity->id(),
      'submitter_name' => $form_state->getValue('submitter_name'),
      'submitter_email' => $form_state->getValue('submitter_email'),
      'submitter_phone' => $form_state->getValue('submitter_phone'),
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

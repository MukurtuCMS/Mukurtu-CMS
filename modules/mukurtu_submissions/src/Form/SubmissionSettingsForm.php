<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding and editing Submission settings entities.
 */
class SubmissionSettingsForm extends EntityForm {

  /**
   * Media type bundles selectable for the public submission form.
   */
  const MEDIA_TYPES = ['image', 'video', 'audio', 'document', 'remote_video', 'external_embed', 'soundcloud'];

  /**
   * Field-type-keyed widget overrides for the public submission form.
   *
   * The public form's audience includes keyboard-only and screen reader
   * visitors, for whom the Leaflet/Geoman map widget (the editorial
   * default for geofield-type fields) has no accessible equivalent - see
   * https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1913. Keyed by field
   * type so it applies to field_coverage (or any other geofield) on every
   * bundle, regardless of what the bundle's own "default" form display
   * uses.
   */
  const SUBMISSION_WIDGET_OVERRIDES = [
    'geofield' => [
      'type' => 'geofield_mukurtu_latlon',
      'settings' => [
        'instructions' => '',
        'show_descriptions' => TRUE,
      ],
    ],
  ];

  /**
   * Constructs a SubmissionSettingsForm object.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    assert($this->entity instanceof SubmissionSettingsInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable public submissions for this content type'),
      '#default_value' => $this->entity->status(),
    ];

    $form['access_level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Who can submit'),
      '#options' => [
        'anonymous' => $this->t('Visitors and authenticated users.'),
        'authenticated' => $this->t('Authenticated users only.'),
      ],
      '#default_value' => $this->entity->getAccessLevel(),
      '#required' => TRUE,
    ];

    $bundle_options = $this->getNodeBundleOptions();
    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $bundle_options,
      '#default_value' => $this->entity->get('target_bundle'),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#description' => $this->t('The content type this form submits.'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'value',
      '#value' => 'node',
    ];

    // Only shown once a bundle is actually assigned - a new, unsaved entity
    // has none yet, so there's nothing to build this list from until it's
    // saved (auto-provisioning seeds every field as included at that point;
    // see ensureSubmissionFormDisplay()).
    if (!$this->entity->isNew()) {
      $entity_type_id = $this->entity->getTargetEntityTypeId();
      $bundle = $this->entity->getTargetBundle();
      $field_options = $this->getIncludableFieldOptions($entity_type_id, $bundle);
      $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
      $included_fields = array_filter(
        array_keys($field_options),
        fn (string $field_name) => (bool) $display->getComponent($field_name),
      );

      $form['included_fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Fields to include on this form'),
        '#description' => $this->t("Choose which of this content type's fields appear on the public submission form. Widget type and field order can still be adjusted via Manage Form Display if needed."),
        '#options' => $field_options,
        '#default_value' => $included_fields,
      ];
    }

    $form['allowed_media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed media types'),
      '#description' => $this->t('Restrict which kinds of media a visitor may attach on the public submission form. Only applies to content types with a media field configured on their Submission form display.'),
      '#options' => $this->getMediaTypeOptions(),
      '#default_value' => $this->entity->getAllowedMediaTypes() ?: self::MEDIA_TYPES,
    ];

    $form['access_expectations_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ask submitters how they expect this item to be shared'),
      '#description' => $this->t('Adds a free-text hint field so submitters can describe their access expectations, to help reviewers choose an appropriate protocol.'),
      '#default_value' => $this->entity->accessExpectationsEnabled(),
    ];

    return $form;
  }

  /**
   * Gets selectable node bundle options.
   */
  protected function getNodeBundleOptions(): array {
    $options = [];
    foreach ($this->entityBundleInfo->getBundleInfo('node') as $bundle => $info) {
      $options[$bundle] = $info['label'];
    }
    return $options;
  }

  /**
   * Gets selectable media type options.
   */
  protected function getMediaTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManagerService->getStorage('media_type')->loadMultiple(self::MEDIA_TYPES) as $id => $media_type) {
      $options[$id] = $media_type->label();
    }
    return $options;
  }

  /**
   * Gets the target bundle's own fields, keyed by name, that a manager may
   * choose to include on the public submission form.
   *
   * Scoped to "field_"-prefixed fields only - the content type's own custom
   * fields - excluding PublicSubmissionForm::EXCLUDED_FIELDS (which stay
   * hidden regardless of this choice). Base/administrative fields like
   * title, uid, or moderation_state are never offered here: title can't
   * meaningfully be excluded (Drupal requires it), and the rest are
   * permanently excluded already.
   */
  protected function getIncludableFieldOptions(string $entity_type_id, string $bundle): array {
    $options = [];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $definition) {
      if (!str_starts_with($field_name, 'field_') || in_array($field_name, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE)) {
        continue;
      }
      $options[$field_name] = $definition->getLabel();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $allowed_media_types = array_filter($form_state->getValue('allowed_media_types') ?? []);
    $form_state->setValue('allowed_media_types', array_values($allowed_media_types));
  }

  /**
   * Determines if the entity already exists.
   */
  public function exists($id): bool {
    return (bool) $this->entityTypeManagerService->getStorage('mukurtu_submission_settings')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    if ($result == SAVED_NEW) {
      $this->ensureSubmissionFormDisplay();
    }
    elseif ($form_state->hasValue('included_fields')) {
      $this->syncIncludedFields($form_state);
    }

    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created submission settings for %label.', $message_args)
      : $this->t('Updated submission settings for %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Creates a "submission" form display for the target bundle if none
   * exists yet, so a newly enabled content type is usable immediately
   * instead of being blocked by SubmissionAccessCheck's missing-display
   * safeguard. Seeded from whichever fields the bundle already exposes -
   * EntityDisplayRepository::getFormDisplay() auto-populates a fresh,
   * unsaved "submission" mode with every field, same as it would for any
   * other not-yet-configured form mode - minus the fields the public form
   * never shows regardless of bundle.
   *
   * Note: "revision_log" doesn't declare itself form-display-configurable
   * (unlike uid/created/status/path/moderation_state, which do), so Drupal
   * silently re-adds it as visible on every load regardless of what's
   * hidden here or in Field UI - PublicSubmissionForm::buildForm() strips
   * it again at render time, which is what actually keeps it off the
   * public form; the removeComponent() call here mainly keeps this
   * display's saved config consistent with what PublicSubmissionForm
   * enforces for every other excluded field.
   */
  protected function ensureSubmissionFormDisplay(): void {
    assert($this->entity instanceof SubmissionSettingsInterface);
    $display = $this->entityDisplayRepository->getFormDisplay(
      $this->entity->getTargetEntityTypeId(),
      $this->entity->getTargetBundle(),
      'submission',
    );
    if (!$display->isNew()) {
      return;
    }

    foreach (PublicSubmissionForm::EXCLUDED_FIELDS as $excluded_field) {
      $display->removeComponent($excluded_field);
    }
    foreach (array_keys($this->getIncludableFieldOptions($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle())) as $field_name) {
      $this->applySubmissionWidgetOverride($display, $this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle(), $field_name);
    }
    $display->save();
  }

  /**
   * Applies SUBMISSION_WIDGET_OVERRIDES to a form display component, if the
   * field's type has an override and the display has a component for it.
   */
  protected function applySubmissionWidgetOverride(EntityFormDisplayInterface $display, string $entity_type_id, string $bundle, string $field_name): void {
    $component = $display->getComponent($field_name);
    if (!$component) {
      return;
    }
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $field_type = $field_definitions[$field_name]->getType() ?? NULL;
    if ($field_type !== NULL && isset(self::SUBMISSION_WIDGET_OVERRIDES[$field_type])) {
      $display->setComponent($field_name, self::SUBMISSION_WIDGET_OVERRIDES[$field_type] + $component);
    }
  }

  /**
   * Applies the "Fields to include on this form" checklist to the target
   * bundle's "submission" form display: a checked field becomes visible
   * (reusing its widget from the bundle's "default" form display if it
   * isn't already a component here), an unchecked field is hidden.
   */
  protected function syncIncludedFields(FormStateInterface $form_state): void {
    assert($this->entity instanceof SubmissionSettingsInterface);
    $entity_type_id = $this->entity->getTargetEntityTypeId();
    $bundle = $this->entity->getTargetBundle();
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
    $default_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default');
    $included = array_filter($form_state->getValue('included_fields') ?? []);

    foreach ($this->getIncludableFieldOptions($entity_type_id, $bundle) as $field_name => $label) {
      if (isset($included[$field_name])) {
        if (!$display->getComponent($field_name)) {
          $display->setComponent($field_name, $default_display->getComponent($field_name) ?: []);
        }
        $this->applySubmissionWidgetOverride($display, $entity_type_id, $bundle, $field_name);
      }
      else {
        $display->removeComponent($field_name);
      }
    }
    $display->save();
  }

}

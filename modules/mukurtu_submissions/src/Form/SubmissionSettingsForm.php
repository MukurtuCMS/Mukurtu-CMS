<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
   * Constructs a SubmissionSettingsForm object.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
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
      '#description' => $this->t('The content type this form submits. Field visibility and widgets are configured via that content type\'s "Submission" form display (Manage Form Display).'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'value',
      '#value' => 'node',
    ];

    // Only shown once a bundle is actually assigned - a new, unsaved entity
    // has none yet, and there's nothing to link to until it's saved.
    if (!$this->entity->isNew()) {
      $manage_form_display_url = Url::fromRoute('entity.entity_form_display.node.form_mode', [
        'node_type' => $this->entity->getTargetBundle(),
        'form_mode_name' => 'submission',
      ]);
      if ($manage_form_display_url->access($this->currentUser())) {
        $form['manage_form_display'] = [
          '#type' => 'link',
          '#title' => $this->t('Manage fields shown on this form'),
          '#url' => $manage_form_display_url,
        ];
      }
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
    $display->save();
  }

}

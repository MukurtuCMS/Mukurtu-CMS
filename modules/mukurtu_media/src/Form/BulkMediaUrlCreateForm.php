<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a two-step form for creating multiple URL-based media items at once.
 *
 * Step 1: Enter one URL per line.
 * Step 2: Set protocols and metadata for each item, then save.
 */
class BulkMediaUrlCreateForm extends FormBase implements ContainerInjectionInterface {

  protected const SUPPORTED_TYPES = [
    'remote_video' => [
      'field' => 'field_media_oembed_video',
      'label' => 'remote videos',
    ],
    'soundcloud' => [
      'field' => 'field_media_soundcloud',
      'label' => 'SoundCloud items',
    ],
  ];

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  public function getFormId(): string {
    return 'mukurtu_media_bulk_url_create_form';
  }

  public static function getTitle(string $media_type): string {
    $label = self::SUPPORTED_TYPES[$media_type]['label'] ?? $media_type;
    return "Bulk add {$label}";
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $media_type = ''): array {
    if (!isset(self::SUPPORTED_TYPES[$media_type])) {
      $this->messenger()->addError($this->t('Bulk create is not supported for this media type.'));
      return $form;
    }

    $form_state->set('media_type', $media_type);
    $form['#tree'] = TRUE;

    $entities = $form_state->get('bulk_create_entities') ?? [];

    if (empty($entities)) {
      return $this->buildUrlStep($form, $form_state, $media_type);
    }

    return $this->buildMetadataStep($form, $form_state, $entities, $media_type);
  }

  protected function buildUrlStep(array $form, FormStateInterface $form_state, string $media_type): array {
    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs'),
      '#description' => $this->t('Enter one URL per line. You will then set protocols and metadata for each item before saving.'),
      '#rows' => 10,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to metadata'),
      '#submit' => ['::createEntitiesSubmit'],
      '#limit_validation_errors' => [['urls']],
      '#button_type' => 'primary',
    ];

    $cancel_url = Url::fromRoute('entity.media.add_form', ['media_type' => $media_type]);
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $cancel_url,
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  protected function buildMetadataStep(array $form, FormStateInterface $form_state, array $entities, string $media_type): array {
    $field_name = self::SUPPORTED_TYPES[$media_type]['field'];
    $count = count($entities);

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Set protocols and metadata for @count item(s) below, then click Save all.', ['@count' => $count]),
    ];

    $form['entities'] = ['#type' => 'container'];

    foreach ($entities as $delta => $entity) {
      $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');

      $form['entities'][$delta] = [
        '#type' => 'details',
        '#title' => $entity->getName(),
        '#open' => TRUE,
      ];

      $form['entities'][$delta]['fields'] = [
        '#type' => 'container',
        '#parents' => ['entities', $delta, 'fields'],
      ];

      // Show the URL as a read-only label.
      $form['entities'][$delta]['url_display'] = [
        '#type' => 'item',
        '#title' => $this->t('URL'),
        '#markup' => htmlspecialchars($entity->get($field_name)->value ?? '', ENT_QUOTES, 'UTF-8'),
        '#weight' => -10,
      ];

      $display->buildForm($entity, $form['entities'][$delta]['fields'], $form_state);

      // Hide the URL input widget — the URL is already set and shown above.
      if (isset($form['entities'][$delta]['fields'][$field_name])) {
        $form['entities'][$delta]['fields'][$field_name]['#access'] = FALSE;
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save all'),
      '#button_type' => 'primary',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::backToUrls'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Submit handler for the "Continue to metadata" button.
   *
   * Creates unsaved Media entities from the entered URLs and stores them in
   * form state so buildForm() can render the metadata step.
   */
  public function createEntitiesSubmit(array &$form, FormStateInterface $form_state): void {
    $media_type = $form_state->get('media_type');
    $config = self::SUPPORTED_TYPES[$media_type];
    $field_name = $config['field'];

    $raw = $form_state->getValue('urls', '');
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    if (empty($lines)) {
      $this->messenger()->addError($this->t('No URLs were provided.'));
      return;
    }

    $entities = [];
    foreach ($lines as $url) {
      $name = $this->nameFromUrl($url);
      $entities[] = Media::create([
        'bundle' => $media_type,
        'uid' => $this->currentUser->id(),
        'name' => $name,
        $field_name => $url,
        'status' => 1,
      ]);
    }

    $form_state->set('bulk_create_entities', $entities);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Back" button.
   */
  public function backToUrls(array &$form, FormStateInterface $form_state): void {
    $form_state->set('bulk_create_entities', NULL);
    $form_state->setRebuild(TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Intermediate step buttons (next, back) set #limit_validation_errors;
    // skip entity validation for those.
    $triggering = $form_state->getTriggeringElement();
    if (isset($triggering['#limit_validation_errors'])) {
      return;
    }

    $entities = $form_state->get('bulk_create_entities') ?? [];
    if (empty($entities)) {
      return;
    }

    foreach ($entities as $delta => $entity) {
      $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
      $display->extractFormValues($entity, $form['entities'][$delta]['fields'], $form_state);
      $display->validateFormValues($entity, $form['entities'][$delta]['fields'], $form_state);
    }

    $form_state->set('bulk_create_entities', $entities);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entities = $form_state->get('bulk_create_entities') ?? [];
    if (empty($entities)) {
      return;
    }

    $created = [];
    $failed = [];

    foreach ($entities as $delta => $entity) {
      $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
      $display->extractFormValues($entity, $form['entities'][$delta]['fields'], $form_state);

      try {
        $entity->save();
        $created[] = $entity;
      }
      catch (\Exception $e) {
        $this->getLogger('mukurtu_media')->error('Failed to save bulk media entity: @message', [
          '@message' => $e->getMessage(),
        ]);
        $failed[] = $entity->getName();
      }
    }

    if (!empty($created)) {
      $count = count($created);
      $this->messenger()->addStatus($this->t('@count media item(s) created successfully.', ['@count' => $count]));
      if ($count <= 10) {
        foreach ($created as $media) {
          $edit_url = $media->toUrl('edit-form');
          $this->messenger()->addStatus($this->t('Created: <a href="@url">@name</a>', [
            '@url' => $edit_url->toString(),
            '@name' => $media->getName(),
          ]));
        }
      }
    }

    if (!empty($failed)) {
      $this->messenger()->addError($this->t('@count item(s) could not be saved.', ['@count' => count($failed)]));
    }

    $form_state->setRedirectUrl(Url::fromRoute('entity.media.collection'));
  }

  /**
   * Derives a human-readable name from a URL.
   */
  protected function nameFromUrl(string $url): string {
    $parts = parse_url($url);
    if (!empty($parts['path'])) {
      $segments = array_filter(explode('/', trim($parts['path'], '/')));
      if (!empty($segments)) {
        $last = end($segments);
        $last = preg_replace('/[?#].*$/', '', $last);
        $name = urldecode($last);
        if ($name !== '') {
          return $name;
        }
      }
    }
    return $url;
  }

}

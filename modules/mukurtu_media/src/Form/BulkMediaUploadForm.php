<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a two-step form for uploading multiple media assets at once.
 *
 * Step 1: Select files to upload.
 * Step 2: Set protocols and metadata for each uploaded file, then save.
 */
class BulkMediaUploadForm extends FormBase implements ContainerInjectionInterface {

  /**
   * File-based media types supported by this form.
   */
  protected const SUPPORTED_TYPES = [
    'image' => [
      'field' => 'field_media_image',
      'extensions' => 'png gif jpg jpeg webp',
      'uri_scheme' => 'private',
      'alt' => TRUE,
    ],
    'audio' => [
      'field' => 'field_media_audio_file',
      'extensions' => 'mp3 wav aac m4a ogg',
      'uri_scheme' => 'private',
    ],
    'document' => [
      'field' => 'field_media_document',
      'extensions' => 'txt rtf doc docx ppt pptx xls xlsx pdf odf odg odp ods odt fodt fods fodp fodg key numbers pages csv sxw zip rar gz 7z tar',
      'uri_scheme' => 'private',
    ],
    'video' => [
      'field' => 'field_media_video_file',
      'extensions' => 'mp4 webm ogv',
      'uri_scheme' => 'private',
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
    return 'mukurtu_media_bulk_upload_form';
  }

  public static function getTitle(string $media_type): string {
    $labels = [
      'image' => 'images',
      'audio' => 'audio files',
      'document' => 'documents',
      'video' => 'videos',
    ];
    $label = $labels[$media_type] ?? $media_type;
    return "Bulk upload {$label}";
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $media_type = ''): array {
    if (!isset(self::SUPPORTED_TYPES[$media_type])) {
      $this->messenger()->addError($this->t('Bulk upload is not supported for this media type.'));
      return $form;
    }

    $config = self::SUPPORTED_TYPES[$media_type];
    $form_state->set('media_type', $media_type);
    $form['#tree'] = TRUE;

    $entities = $form_state->get('bulk_upload_entities') ?? [];

    if (empty($entities)) {
      return $this->buildUploadStep($form, $form_state, $config, $media_type);
    }

    return $this->buildMetadataStep($form, $form_state, $entities, $config, $media_type);
  }

  protected function buildUploadStep(array $form, FormStateInterface $form_state, array $config, string $media_type): array {
    $year = date('Y');
    $month = date('m');

    $form['#attached']['library'][] = 'mukurtu_media/bulk_upload_dropzone';

    $form['dropzone'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-bulk-dropzone']],
    ];
    $max_size = format_size(\Drupal\Component\Utility\Environment::getUploadMaxSize());
    $form['dropzone']['hint'] = [
      '#markup' => '<p class="mukurtu-bulk-dropzone__hint">' . $this->t('Drop files here, or use Choose Files to select files.') . '</p>',
    ];
    $form['dropzone']['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Choose files'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Allowed types: @extensions. Maximum file size: @size.', [
        '@extensions' => $config['extensions'],
        '@size' => $max_size,
      ]),
      '#multiple' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => $config['extensions']],
      ],
      '#upload_location' => $config['uri_scheme'] . "://{$year}-{$month}",
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to metadata'),
      '#submit' => ['::uploadFilesSubmit'],
      '#limit_validation_errors' => [['dropzone']],
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

  protected function buildMetadataStep(array $form, FormStateInterface $form_state, array $entities, array $config, string $media_type): array {
    $field_name = $config['field'];
    $count = count($entities);

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Set protocols and metadata for @count media item(s) below, then click Save all.', ['@count' => $count]),
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

      $display->buildForm($entity, $form['entities'][$delta]['fields'], $form_state);
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
      '#submit' => ['::backToUpload'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Submit handler for the "Continue to metadata" button.
   *
   * Creates unsaved Media entities from the uploaded files and stores them in
   * form state so buildForm() can render the metadata step.
   */
  public function uploadFilesSubmit(array &$form, FormStateInterface $form_state): void {
    $media_type = $form_state->get('media_type');
    $config = self::SUPPORTED_TYPES[$media_type];
    $field_name = $config['field'];
    $needs_alt = $config['alt'] ?? FALSE;

    $fids = $form_state->getValue(['dropzone', 'upload'], []);
    if (empty($fids)) {
      $this->messenger()->addError($this->t('No files were uploaded.'));
      return;
    }

    $entities = [];
    foreach ($fids as $fid) {
      $file = File::load($fid);
      if (!$file) {
        continue;
      }

      $filename = $file->getFilename();
      $last_dot = strrpos($filename, '.');
      $name = $last_dot !== FALSE ? substr($filename, 0, $last_dot) : $filename;

      $field_value = ['target_id' => $fid];
      if ($needs_alt) {
        $field_value['alt'] = $name;
      }

      $entities[] = Media::create([
        'bundle' => $media_type,
        'uid' => $this->currentUser->id(),
        'name' => $name,
        $field_name => $field_value,
        'status' => 1,
      ]);
    }

    if (empty($entities)) {
      $this->messenger()->addError($this->t('Could not load any of the uploaded files.'));
      return;
    }

    $form_state->set('bulk_upload_entities', $entities);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Back" button.
   */
  public function backToUpload(array &$form, FormStateInterface $form_state): void {
    $form_state->set('bulk_upload_entities', NULL);
    $form_state->setRebuild(TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Intermediate step buttons (upload, back) set #limit_validation_errors;
    // skip entity validation for those.
    $triggering = $form_state->getTriggeringElement();
    if (isset($triggering['#limit_validation_errors'])) {
      return;
    }

    $entities = $form_state->get('bulk_upload_entities') ?? [];
    if (empty($entities)) {
      return;
    }

    foreach ($entities as $delta => $entity) {
      $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
      $display->extractFormValues($entity, $form['entities'][$delta]['fields'], $form_state);
      $display->validateFormValues($entity, $form['entities'][$delta]['fields'], $form_state);
    }

    $form_state->set('bulk_upload_entities', $entities);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entities = $form_state->get('bulk_upload_entities') ?? [];
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

}

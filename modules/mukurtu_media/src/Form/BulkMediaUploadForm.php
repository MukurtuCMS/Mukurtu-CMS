<?php

namespace Drupal\mukurtu_media\Form;

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
 * Provides a form for uploading multiple media assets at once.
 */
class BulkMediaUploadForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The file-based media types supported by this form and their config.
   *
   * Keys are bundle IDs. Values are arrays with 'field', 'extensions',
   * 'uri_scheme', and optionally 'alt' (TRUE if alt text must be set).
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

  /**
   * Returns the page title for the route title callback.
   */
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
      $form['#attached']['http_header'][] = ['Location', Url::fromRoute('view.mukurtu_media_library.page_1')->toString()];
      return $form;
    }

    $config = self::SUPPORTED_TYPES[$media_type];
    $form_state->set('media_type', $media_type);

    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Select multiple files to upload. A separate media item will be created for each file. Names will be filled in automatically from the filenames.'),
    ];

    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Files'),
      '#description' => $this->t('Allowed file types: @extensions.', ['@extensions' => $config['extensions']]),
      '#multiple' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => $config['extensions']],
      ],
      '#upload_location' => $config['uri_scheme'] . '://[date:custom:Y]-[date:custom:m]',
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
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

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $media_type = $form_state->get('media_type');
    $config = self::SUPPORTED_TYPES[$media_type];
    $field_name = $config['field'];
    $needs_alt = $config['alt'] ?? FALSE;

    $fids = $form_state->getValue('upload', []);
    if (empty($fids)) {
      $this->messenger()->addError($this->t('No files were uploaded.'));
      return;
    }

    $created = [];
    $failed = [];

    foreach ($fids as $fid) {
      $file = File::load($fid);
      if (!$file) {
        $failed[] = $fid;
        continue;
      }

      $filename = $file->getFilename();
      $last_dot = strrpos($filename, '.');
      $name = $last_dot !== FALSE ? substr($filename, 0, $last_dot) : $filename;

      $field_value = ['target_id' => $fid];
      if ($needs_alt) {
        $field_value['alt'] = $name;
      }

      $media = Media::create([
        'bundle' => $media_type,
        'uid' => $this->currentUser->id(),
        'name' => $name,
        $field_name => $field_value,
        'status' => 1,
      ]);

      try {
        $media->save();
        $created[] = $media;
      }
      catch (\Exception $e) {
        $this->getLogger('mukurtu_media')->error('Failed to create media entity for file @fid: @message', [
          '@fid' => $fid,
          '@message' => $e->getMessage(),
        ]);
        $failed[] = $filename;
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
      $this->messenger()->addError($this->t('@count file(s) could not be processed.', ['@count' => count($failed)]));
    }

    $form_state->setRedirectUrl(Url::fromRoute('entity.media.collection'));
  }

}

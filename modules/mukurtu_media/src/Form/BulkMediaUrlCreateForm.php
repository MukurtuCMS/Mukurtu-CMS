<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for creating multiple URL-based media items at once.
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

    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs'),
      '#description' => $this->t('Enter one URL per line. A separate media item will be created for each URL.'),
      '#rows' => 10,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create'),
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

    $raw = $form_state->getValue('urls', '');
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    if (empty($lines)) {
      $this->messenger()->addError($this->t('No URLs were provided.'));
      return;
    }

    $created = [];
    $failed = [];

    foreach ($lines as $url) {
      $name = $this->nameFromUrl($url);

      $media = Media::create([
        'bundle' => $media_type,
        'uid' => $this->currentUser->id(),
        'name' => $name,
        $field_name => $url,
        'status' => 1,
      ]);

      try {
        $media->save();
        $created[] = $media;
      }
      catch (\Exception $e) {
        $this->getLogger('mukurtu_media')->error('Failed to create media entity for URL @url: @message', [
          '@url' => $url,
          '@message' => $e->getMessage(),
        ]);
        $failed[] = $url;
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
      $this->messenger()->addError($this->t('@count URL(s) could not be processed.', ['@count' => count($failed)]));
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
        // Remove query-string-like suffixes and decode.
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

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_pwa\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tester pages and result endpoints for the #400 PWA spike. Not for release.
 *
 * Results go to the server, not only the device: if a phone deletes the
 * app's storage, its own record of the test goes with it.
 */
final class SpikeController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The most reports kept, so a runaway device can't fill the database.
   */
  const MAX_REPORTS = 5000;

  /**
   * The largest test upload accepted, in bytes.
   */
  const MAX_UPLOAD = 20 * 1024 * 1024;

  public function __construct(
    protected KeyValueFactoryInterface $keyValue,
    protected FileSystemInterface $fileSystem,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('keyvalue'),
      $container->get('file_system'),
      $container->get('current_user'),
      $container->get('datetime.time'),
    );
  }

  /**
   * The spike's result store.
   */
  protected function store(): KeyValueStoreInterface {
    return $this->keyValue->get('mukurtu_pwa_spike');
  }

  /**
   * Shows what this device has stored and lets testers fill storage.
   */
  public function status(): array {
    $rows = [
      'usage' => $this->t('Storage used: @used of about @quota', ['@used' => '…', '@quota' => '…']),
      'persisted' => $this->t('Persistent storage: @value', ['@value' => '…']),
      'installed' => $this->t('Installed as an app: @value', ['@value' => '…']),
      'version' => $this->t('Service worker version: @version', ['@version' => '…']),
      'items' => $this->t('Items kept offline: @count', ['@count' => '…']),
    ];
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-pwa-spike', 'mukurtu-pwa-status']],
      '#attached' => ['library' => ['mukurtu_pwa/spike']],
    ];
    $build['facts'] = [
      '#type' => 'html_tag',
      '#tag' => 'ul',
      '#attributes' => ['class' => ['mukurtu-pwa-status__facts']],
    ];
    foreach ($rows as $key => $text) {
      $build['facts'][$key] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#value' => $text,
        '#attributes' => ['data-status' => $key],
      ];
    }
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-pwa-status__actions']],
    ];
    $actions = [
      'persist' => $this->t('Ask to keep storage'),
      'fill' => $this->t('Store 100 MB of test data'),
      'clear' => $this->t('Remove test data'),
    ];
    foreach ($actions as $action => $label) {
      $build['actions'][$action] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $label,
        '#attributes' => ['type' => 'button', 'class' => ['button'], 'data-action' => $action],
      ];
    }
    $build['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => '',
      '#attributes' => ['role' => 'status', 'class' => ['mukurtu-pwa-spike__message']],
    ];
    $build['opens_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('App opens'),
    ];
    $build['opens'] = [
      '#type' => 'table',
      '#header' => [$this->t('Date'), $this->t('Kept items found'), $this->t('Storage used')],
      '#rows' => [],
      '#attributes' => ['class' => ['mukurtu-pwa-status__opens']],
    ];
    return $build;
  }

  /**
   * A form that saves drafts on the device and uploads them later.
   */
  public function outbox(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-pwa-spike', 'mukurtu-pwa-outbox']],
      '#attached' => ['library' => ['mukurtu_pwa/spike']],
      'form' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => ['class' => ['mukurtu-pwa-outbox__form'], 'novalidate' => 'novalidate'],
        'title' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => $this->t('Title'),
            '#attributes' => [
              'for' => 'mukurtu-pwa-outbox-title',
              'class' => ['form-item__label', 'form-required'],
            ],
          ],
          'input' => [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => [
              'type' => 'text',
              'id' => 'mukurtu-pwa-outbox-title',
              'name' => 'title',
              'required' => 'required',
              'class' => ['form-text', 'form-element'],
            ],
          ],
        ],
        'photo' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => $this->t('Photo'),
            '#attributes' => [
              'for' => 'mukurtu-pwa-outbox-photo',
              'class' => ['form-item__label', 'form-required'],
            ],
          ],
          'input' => [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => [
              'type' => 'file',
              'id' => 'mukurtu-pwa-outbox-photo',
              'name' => 'photo',
              'accept' => 'image/*',
              'required' => 'required',
              'class' => ['form-file', 'form-element'],
            ],
          ],
        ],
        'submit' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Save draft'),
          '#attributes' => ['type' => 'submit', 'class' => ['button', 'button--primary']],
        ],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => '',
        '#attributes' => ['role' => 'status', 'class' => ['mukurtu-pwa-spike__message']],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Drafts waiting to upload'),
      ],
      'list' => [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#value' => '',
        '#attributes' => ['class' => ['mukurtu-pwa-outbox__list']],
      ],
    ];
  }

  /**
   * Receives a draft from a device's outbox.
   *
   * Uploads are idempotent by the device's draft ID, so a retry after a
   * dropped connection never records the same draft twice. Files are stored
   * privately and never served.
   */
  public function upload(Request $request): JsonResponse {
    $clientId = (string) $request->request->get('client_id');
    $title = trim((string) $request->request->get('title'));
    $photo = $request->files->get('photo');
    if (!Uuid::isValid($clientId) || $title === '' || !$photo || !$photo->isValid() || $photo->getSize() > self::MAX_UPLOAD) {
      return new JsonResponse(['error' => 'invalid'], 400);
    }

    $received = $this->store()->get('received', []);
    if (isset($received[$clientId])) {
      return new JsonResponse(['ok' => TRUE, 'duplicate' => TRUE]);
    }

    $directory = 'private://pwa-spike';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->copy($photo->getPathname(), "$directory/$clientId.bin", FileExists::Replace);

    $received[$clientId] = [
      'title' => mb_substr($title, 0, 255),
      'uid' => (int) $this->currentUser->id(),
      'bytes' => (int) $photo->getSize(),
      'created' => (int) $request->request->get('created'),
      'received' => $this->time->getRequestTime(),
    ];
    $this->store()->set('received', $received);
    return new JsonResponse(['ok' => TRUE, 'duplicate' => FALSE]);
  }

  /**
   * Records one app open from a device.
   */
  public function report(Request $request): JsonResponse {
    $data = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($data) || !Uuid::isValid((string) ($data['deviceId'] ?? ''))) {
      return new JsonResponse(['error' => 'invalid'], 400);
    }
    $allowed = [
      'deviceId', 'deviceFirstSeen', 'time', 'standalone', 'itemsTotal',
      'itemsFound', 'usage', 'quota', 'persisted', 'swVersion', 'userAgent',
    ];
    $report = array_intersect_key($data, array_flip($allowed));
    $report['userAgent'] = mb_substr((string) ($report['userAgent'] ?? ''), 0, 300);
    $report['uid'] = (int) $this->currentUser->id();
    $report['received'] = $this->time->getRequestTime();

    $reports = $this->store()->get('reports', []);
    $reports[] = $report;
    $this->store()->set('reports', array_slice($reports, -self::MAX_REPORTS));
    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * Returns every report and received draft, for writing up the results.
   */
  public function results(): JsonResponse {
    return new JsonResponse([
      'reports' => $this->store()->get('reports', []),
      'received' => $this->store()->get('received', []),
    ]);
  }

}

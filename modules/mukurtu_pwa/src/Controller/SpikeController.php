<?php

declare(strict_types=1);

namespace Drupal\mukurtu_pwa\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tester pages and result endpoints for the #400 PWA spike. Not for release.
 *
 * Results go to the server, not only the device: if a phone deletes the
 * app's storage, its own record of the test goes with it. They're kept in a
 * private file rather than the database, because a Tugboat rebuild reinstalls
 * the site but leaves private files in place.
 */
final class SpikeController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The most reports kept, so a runaway device can't fill the disk.
   */
  const MAX_REPORTS = 5000;

  /**
   * The largest test upload accepted, in bytes.
   */
  const MAX_UPLOAD = 20 * 1024 * 1024;

  /**
   * The most rejected uploads kept.
   */
  const MAX_REJECTED = 200;

  /**
   * Where reports, received drafts and their photos are kept.
   */
  const DIRECTORY = 'private://pwa-spike';

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('current_user'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Reads the results file.
   *
   * @return array{reports: array, received: array, rejected: array}
   *   Every report, received draft and rejected upload.
   */
  public function readResults(): array {
    $path = self::DIRECTORY . '/results.json';
    $data = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
    return (is_array($data) ? $data : []) + ['reports' => [], 'received' => [], 'rejected' => []];
  }

  /**
   * Changes the results file under an exclusive lock.
   *
   * The lock stops two phones reporting at the same moment from overwriting
   * each other's results.
   *
   * @param callable $change
   *   Receives the results by reference and returns a value to pass back.
   *
   * @return mixed
   *   Whatever $change returned.
   */
  protected function updateResults(callable $change): mixed {
    $directory = self::DIRECTORY;
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $handle = fopen("$directory/results.json", 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
      throw new \RuntimeException('Unable to lock the spike results file.');
    }
    try {
      $data = json_decode((string) stream_get_contents($handle), TRUE);
      $data = (is_array($data) ? $data : []) + ['reports' => [], 'received' => [], 'rejected' => []];
      $result = $change($data);
      ftruncate($handle, 0);
      rewind($handle);
      fwrite($handle, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
      fflush($handle);
      return $result;
    }
    finally {
      flock($handle, LOCK_UN);
      fclose($handle);
    }
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
    $reason = match (TRUE) {
      !Uuid::isValid($clientId) => 'client_id',
      $title === '' => 'title',
      !$photo => 'photo_missing',
      !$photo->isValid() => 'photo_error_' . $photo->getError(),
      $photo->getSize() === 0 => 'photo_empty',
      $photo->getSize() > self::MAX_UPLOAD => 'photo_too_large',
      default => NULL,
    };
    if ($reason !== NULL) {
      // Kept with the results, so a failure on a tester's phone can be
      // diagnosed without the phone (#400).
      $this->updateResults(function (array &$data) use ($reason, $photo, $request): void {
        $data['rejected'][] = [
          'reason' => $reason,
          'uid' => (int) $this->currentUser->id(),
          'bytes' => $photo && $photo->isValid() ? (int) $photo->getSize() : NULL,
          'sentBytes' => (int) $request->request->get('size'),
          'userAgent' => mb_substr((string) $request->headers->get('User-Agent'), 0, 255),
          'received' => $this->time->getRequestTime(),
        ];
        $data['rejected'] = array_slice($data['rejected'], -self::MAX_REJECTED);
      });
      return new JsonResponse(['error' => $reason], 400);
    }

    $duplicate = $this->updateResults(function (array &$data) use ($clientId, $title, $photo, $request): bool {
      if (isset($data['received'][$clientId])) {
        return TRUE;
      }
      $this->fileSystem->copy($photo->getPathname(), self::DIRECTORY . "/$clientId.bin", FileExists::Replace);
      $data['received'][$clientId] = [
        'title' => mb_substr($title, 0, 255),
        'uid' => (int) $this->currentUser->id(),
        'bytes' => (int) $photo->getSize(),
        'created' => (int) $request->request->get('created'),
        'received' => $this->time->getRequestTime(),
      ];
      return FALSE;
    });
    return new JsonResponse(['ok' => TRUE, 'duplicate' => $duplicate]);
  }

  /**
   * Records one app open from a device.
   *
   * Signed-out devices can report too: a Tugboat rebuild signs every phone
   * out, and the retention test must keep counting through that. The device
   * ID ties one phone's reports together.
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

    $this->updateResults(function (array &$data) use ($report): void {
      $data['reports'][] = $report;
      $data['reports'] = array_slice($data['reports'], -self::MAX_REPORTS);
    });
    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * Returns every report and received draft, for writing up the results.
   */
  public function results(): JsonResponse {
    return new JsonResponse($this->readResults());
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_import\MukurtuImportLogStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the Mukurtu Import Logs pages.
 */
class ImportLogController extends ControllerBase {

  const PERMISSION_VIEW_ANY = 'view any mukurtu_import_log';

  public function __construct(
    protected MukurtuImportLogStorage $logStorage,
    protected EntityTypeManagerInterface $importEntityTypeManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_import.log_storage'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays a listing of import log entries.
   */
  public function overview(Request $request): array {
    $show_all = $this->currentUser()->hasPermission(self::PERMISSION_VIEW_ANY);

    $header = [
      [
        'data' => $this->t('Date'),
        'field' => 'l.timestamp',
        'sort' => 'desc',
      ],
      [
        'data' => $this->t('Status'),
        'field' => 'l.success',
      ],
      [
        'data' => $this->t('Filename'),
        'field' => 'l.filename',
      ],
      [
        'data' => $this->t('Destination'),
      ],
      [
        'data' => $this->t('Rows'),
      ],
    ];
    if ($show_all) {
      $header[] = [
        'data' => $this->t('User'),
        'field' => 'l.uid',
      ];
    }
    $header[] = [
      'data' => $this->t('Operations'),
    ];

    $query = $this->logStorage->query()
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    if (!$show_all) {
      $query->condition('l.uid', $this->currentUser()->id());
    }
    $result = $query
      ->limit(50)
      ->orderByHeader($header)
      ->execute();

    $rows = [];
    foreach ($result as $log) {
      $status = $log->success
        ? $this->t('Success')
        : $this->t('Failed');

      $row = [
        $this->dateFormatter->format((int) $log->timestamp, 'short'),
        $status,
        $log->filename,
        $this->buildDestinationLabel($log->entity_type_id, $log->bundle),
        $this->buildRowCountsSummary($log),
      ];
      if ($show_all) {
        $account = $this->importEntityTypeManager->getStorage('user')->load($log->uid);
        $row[] = [
          'data' => [
            '#theme' => 'username',
            '#account' => $account,
          ],
        ];
      }
      $has_details = !empty($log->messages) || (int) $log->count_created > 0 || (int) $log->count_updated > 0 || (int) $log->count_failed > 0;
      $details_text = $this->t('Details <span class="visually-hidden">for @filename</span>', ['@filename' => $log->filename]);
      $row[] = $has_details
        ? Link::fromTextAndUrl($details_text, Url::fromRoute('mukurtu_import.import_log_detail', ['id' => $log->id]))->toString()
        : '';
      $rows[] = $row;
    }

    $build['import_log_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['mukurtu-import-log']],
      '#empty' => $this->t('No import history recorded yet.'),
    ];
    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Displays details for a single import log entry.
   */
  public function detail(int $id): array {
    $log = $this->logStorage->load($id);
    if (!$log) {
      throw new NotFoundHttpException();
    }

    $status = $log->success ? $this->t('Success') : $this->t('Failed');

    $build['summary'] = [
      '#type' => 'table',
      '#caption' => $this->t('Import Details'),
      '#header' => [$this->t('Field'), $this->t('Value')],
      '#rows' => [
        [$this->t('Filename'), $log->filename],
        [$this->t('Destination'), $this->buildDestinationLabel($log->entity_type_id, $log->bundle)],
        [$this->t('Status'), $status],
        [$this->t('Date'), $this->dateFormatter->format((int) $log->timestamp, 'medium')],
        [$this->t('Rows'), $this->buildRowCountsSummary($log)],
      ],
    ];

    $details = $log->details ? (json_decode((string) $log->details, TRUE) ?: []) : [];
    $created = array_values(array_filter($details, fn(array $entry) => ($entry['status'] ?? NULL) === 'created'));
    $updated = array_values(array_filter($details, fn(array $entry) => ($entry['status'] ?? NULL) === 'updated'));
    $failed = array_values(array_filter($details, fn(array $entry) => ($entry['status'] ?? NULL) === 'failed'));

    if ($created) {
      $build['created_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Created (@count)', ['@count' => count($created)]),
      ];
      $build['created'] = $this->buildEntityList($created);
    }

    if ($updated) {
      $build['updated_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Updated (@count)', ['@count' => count($updated)]),
      ];
      $build['updated'] = $this->buildEntityList($updated);
    }

    if ($failed) {
      $build['failed_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Failed (@count)', ['@count' => count($failed)]),
      ];
      $build['failed'] = $this->buildFailedTable($failed);
    }

    if (!$created && !$updated && !$failed) {
      $build['messages_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Messages'),
      ];
      $message_lines = array_filter(explode("\n", (string) $log->messages));
      $build['messages'] = [
        '#theme' => 'item_list',
        '#items' => $message_lines,
        '#empty' => $this->t('No messages recorded for this file.'),
      ];
    }

    return $build;
  }

  /**
   * Builds a list of created/updated entities, linked where possible.
   */
  protected function buildEntityList(array $entries): array {
    $items = [];
    foreach ($entries as $entry) {
      $label = $entry['label'] ?? $this->t('(unlabeled)');
      $items[] = !empty($entry['url'])
        ? Link::fromTextAndUrl($label, Url::fromUserInput($entry['url']))->toString()
        : $label;
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Builds a Row / Message table for failed rows.
   */
  protected function buildFailedTable(array $entries): array {
    $rows = [];
    foreach ($entries as $entry) {
      $lines = array_filter(explode("\n", (string) ($entry['message'] ?? '')));
      $rows[] = [
        $entry['source_id'] ?? $this->t('(unknown)'),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $lines,
          ],
        ],
      ];
    }
    return [
      '#type' => 'table',
      '#caption' => $this->t('Failed rows'),
      '#header' => [
        ['data' => $this->t('Row'), 'scope' => 'col'],
        ['data' => $this->t('Message'), 'scope' => 'col'],
      ],
      '#rows' => $rows,
    ];
  }

  /**
   * Title callback for the import log detail route.
   */
  public function detailTitle(int $id): string {
    $log = $this->logStorage->load($id);
    if (!$log) {
      return (string) $this->t('Import Log');
    }
    return (string) $this->t('Import Log: @filename', ['@filename' => $log->filename]);
  }

  /**
   * Access callback for the import log detail route.
   */
  public function access(int $id, AccountInterface $account): AccessResultInterface {
    $log = $this->logStorage->load($id);
    if (!$log) {
      // Let the controller 404; don't leak existence via access denial.
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    return AccessResult::allowedIf(
      $account->hasPermission(self::PERMISSION_VIEW_ANY) || (int) $log->uid === (int) $account->id()
    )->setCacheMaxAge(0);
  }

  /**
   * Builds a human-readable "Entity type: Bundle" destination label.
   */
  protected function buildDestinationLabel(string $entity_type_id, ?string $bundle): string {
    if (!$this->importEntityTypeManager->hasDefinition($entity_type_id)) {
      return $entity_type_id;
    }
    $entity_label = $this->importEntityTypeManager->getDefinition($entity_type_id)->getLabel();
    if (empty($bundle)) {
      return (string) $entity_label;
    }
    $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
    $bundle_label = $bundle_info[$bundle]['label'] ?? $bundle;
    return (string) $this->t('@entity_type: @bundle', [
      '@entity_type' => $entity_label,
      '@bundle' => $bundle_label,
    ]);
  }

  /**
   * Builds a compact summary of the row counts for a log entry.
   */
  protected function buildRowCountsSummary(object $log): string {
    return (string) $this->t('@created created, @updated updated, @failed failed, @ignored ignored', [
      '@created' => (int) $log->count_created,
      '@updated' => (int) $log->count_updated,
      '@failed' => (int) $log->count_failed,
      '@ignored' => (int) $log->count_ignored,
    ]);
  }

}

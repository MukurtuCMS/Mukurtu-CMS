<?php

namespace Drupal\search_api\Utility;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;

/**
 * Provides helper methods for indexing items using Drupal's Batch API.
 */
class IndexingBatchHelper implements IndexingBatchHelperInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    protected LockBackendInterface $lockBackend,
    TranslationInterface $stringTranslation,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected MessengerInterface $messenger,
    protected LoggerInterface $logger,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritDoc}
   */
  public function createBatch(
    IndexInterface $index,
    ?int $batch_size = NULL,
    int $limit = -1,
    int $time_limit = -1,
  ): void {
    // Make sure that the indexing lock is available.
    if (!$this->lockBackend->lockMayBeAvailable($index->getLockId())) {
      throw new SearchApiException('Items are being indexed in a different process.');
    }

    // Check if the size should be determined by the index cron limit option.
    if ($batch_size === NULL) {
      // Use the size set by the index.
      $default_cron_limit = $this->configFactory->get('search_api.settings')
        ->get('default_cron_limit');
      $batch_size = $index->getOption('cron_limit', $default_cron_limit);
    }
    // Check if indexing items is allowed.
    if ($index->status() && !$index->isReadOnly() && $batch_size !== 0 && $limit !== 0) {
      $index->setIndexingRequestTime($this->time->getRequestTime());
      // Define the search index batch definition.
      $batch_definition = [
        'operations' => [
          [[$this, 'process'], [$index, $batch_size, $limit, $time_limit]],
        ],
        'finished' => [$this, 'finish'],
        'progress_message' => $this->t('Completed about @percentage% of the indexing operation (@current of @total).'),
      ];
      // Schedule the batch.
      batch_set($batch_definition);
    }
    else {
      $index_label = $index->label();
      throw new SearchApiException("Failed to create a batch with batch size '$batch_size' and limit '$limit' for index '$index_label'.");
    }
  }

  /**
   * {@inheritDoc}
   */
  public function process(
    IndexInterface $index,
    int $batch_size,
    int $limit,
    int $time_limit,
    array|\ArrayAccess &$context,
  ): void {

    // Check if the sandbox should be initialized.
    if (!isset($context['sandbox']['limit'])) {
      // Initialize the sandbox with data which is shared among the batch runs.
      $context['sandbox']['limit'] = $limit;
      $context['sandbox']['batch_size'] = $batch_size;
      if ($time_limit >= 0) {
        $context['sandbox']['time_limit'] = $time_limit;
        $context['sandbox']['time_start'] = time();
      }
    }
    // Check if the results should be initialized.
    if (!isset($context['results']['indexed'])) {
      // Initialize the results with data which is shared among the batch runs.
      $context['results']['indexed'] = 0;
      $context['results']['not indexed'] = 0;
    }
    // Get the remaining item count. When no valid tracker is available then
    // the value will be set to zero which will cause the batch process to
    // stop.
    $remaining_item_count = (int) $index->getTrackerInstanceIfAvailable()?->getRemainingItemsCount();

    // Time limit check.
    if (($context['sandbox']['time_limit'] ?? -1) >= 0) {
      $elapsed_seconds = time() - $context['sandbox']['time_start'];
      if ($elapsed_seconds > $context['sandbox']['time_limit']) {
        $context['finished'] = 1;
        $context['message'] = $this->t('Time limit of @time_limit seconds reached during indexing on @index', [
          '@time_limit' => $context['sandbox']['time_limit'],
          '@index' => $index->label(),
        ]);
        return;
      }
    }

    // Check if an explicit limit needs to be used.
    if ($context['sandbox']['limit'] > -1) {
      // Calculate the remaining amount of items that can be indexed. Note that
      // a minimum is taking between the allowed number of items and the
      // remaining item count to prevent incorrect reporting of not indexed
      // items.
      $actual_limit = min($context['sandbox']['limit'] - $context['results']['indexed'], $remaining_item_count);
    }
    else {
      // Use the remaining item count as actual limit.
      $actual_limit = $remaining_item_count;
    }

    // Store original count of items to be indexed to show progress properly.
    if (empty($context['sandbox']['original_item_count'])) {
      $context['sandbox']['original_item_count'] = $actual_limit;
    }

    // Determine the number of items to index for this run according to the
    // batch size.
    $to_index = $actual_limit;
    if ($context['sandbox']['batch_size'] > 0) {
      $to_index = min($actual_limit, $context['sandbox']['batch_size']);
    }
    // Catch any exception that may occur during indexing.
    try {
      // Index items limited by the given count.
      $indexed = $index->indexItems($to_index);
      // Increment the indexed result and progress.
      $context['results']['indexed'] += $indexed;
      // Display progress message.
      if ($indexed > 0) {
        $context['message'] = $this->formatPlural($context['results']['indexed'], 'Successfully indexed 1 item on @index.', 'Successfully indexed @count items on @index.', ['@index' => $index->label()]);
      }
      // Everything has been indexed?
      if ($indexed === 0 || $context['results']['indexed'] >= $context['sandbox']['original_item_count']) {
        $context['finished'] = 1;
        $context['results']['not indexed'] = $context['sandbox']['original_item_count'] - $context['results']['indexed'];
      }
      else {
        $context['finished'] = ($context['results']['indexed'] / $context['sandbox']['original_item_count']);
      }
    }
    catch (\Exception $e) {
      // Log exception to watchdog and abort the batch job.
      Error::logException($this->logger, $e);
      $context['message'] = $this->t('An error occurred during indexing on @index: @message', ['@index' => $index->label(), '@message' => $e->getMessage()]);
      $context['finished'] = 1;
      $context['results']['not indexed'] = $context['sandbox']['original_item_count'] - $context['results']['indexed'];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function finish(
    bool $success,
    array $results,
    array $operations,
  ): void {
    // Check if the batch job was successful.
    if ($success) {
      // Display the number of items indexed.
      if (!empty($results['indexed'])) {
        // Build the indexed message.
        $indexed_message = $this->formatPlural($results['indexed'], 'Successfully indexed 1 item.', 'Successfully indexed @count items.');
        // Notify user about indexed items.
        $this->messenger->addStatus($indexed_message);
        // Display the number of items not indexed.
        if (!empty($results['not indexed'])) {
          // Build the not indexed message. Concurrent indexing (e.g., by a cron
          // job) could lead to a false warning here, so we need to phrase this
          // carefully.
          $not_indexed_message = $this->t(
            'Number of indexed items is less than expected (by @count). Check the logs if there are still unindexed items.',
            ['@count' => $results['not indexed']],
          );
          // Notify user about not indexed items.
          $this->messenger->addWarning($not_indexed_message);
        }
      }
      else {
        // Notify user about failure to index items.
        $this->messenger->addError($this->t("Couldn't index items. Check the logs for details."));
      }
    }
    else {
      // Notify user about batch job failure.
      $this->messenger->addError($this->t('An error occurred while trying to index items. Check the logs for details.'));
    }
  }

}

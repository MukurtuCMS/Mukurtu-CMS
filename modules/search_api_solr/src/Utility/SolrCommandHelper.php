<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\ConsoleException;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\CommandHelper;
use Drupal\search_api_solr\Controller\SolrConfigSetController;
use Drupal\search_api_solr\Plugin\search_api\tracker\IndexParallel;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use ZipStream\Option\Archive;

/**
 * Provides functionality to be used by CLI tools.
 */
class SolrCommandHelper extends CommandHelper {

  /**
   * The configset controller.
   *
   * @var \Drupal\search_api_solr\Controller\SolrConfigSetController
   */
  protected $configsetController;

  protected $processes = [];

  /**
   * Constructs a CommandHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\search_api_solr\Controller\SolrConfigSetController $configset_controller
   *   The configset controller.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher, SolrConfigSetController $configset_controller) {
    parent::__construct($entity_type_manager, $module_handler, $event_dispatcher);
    $this->configsetController = $configset_controller;
  }

  /**
   * Re-install all Solr Field Types from their yml files.
   */
  public function reinstallFieldtypesCommand() {
    search_api_solr_install_missing_field_types();
  }

  /**
   * Gets the config for a Solr search server.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param string|null $file_name
   *   The file name of the config zip that should be created.
   * @param string|null $solr_version
   *   The targeted Solr version.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   * @throws \ZipStream\Exception\OverflowException
   */
  public function getServerConfigCommand($server_id, ?string $file_name = NULL, ?string $solr_version = NULL) {
    $server = $this->getServer($server_id);

    if ($solr_version) {
      $config = $server->getBackendConfig();
      // Temporarily switch the Solr version but don't save!
      $config['connector_config']['solr_version'] = $solr_version;
      $server->setBackendConfig($config);
    }
    $this->configsetController->setServer($server);

    $stream = NULL;
    if ($file_name !== NULL) {
      // If no filename is provided, output stream is standard output.
      $stream = fopen($file_name, 'w+b');
    }

    if (class_exists('\ZipStream\Option\Archive')) {
      // Version 2.x.
      $archive_options_or_ressource = new Archive();
      $archive_options_or_ressource->setOutputStream($stream);
    }
    else {
      // Version 3.x.
      $archive_options_or_ressource = $stream;
    }

    $zip = $this->configsetController->getConfigZip($archive_options_or_ressource);
    $zip->finish();

    if ($stream) {
      fclose($stream);
    }
  }

  /**
   * Finalizes one or more indexes.
   *
   * @param string[]|null $indexIds
   *   (optional) An array of index IDs, or NULL if we should finalize all
   *   enabled indexes.
   * @param bool $force
   *   (optional) Force the finalization, even if the index isn't "dirty".
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function finalizeIndexCommand(?array $indexIds = NULL, $force = FALSE) {
    $servers = search_api_solr_get_servers();

    if ($force) {
      // It's important to mark all indexes as "dirty" before the first
      // finalization runs because there might be dependencies between the
      // indexes. Therefor we do the loop two times.
      foreach ($servers as $server) {
        foreach ($server->getIndexes() as $index) {
          if ($index->status() && !$index->isReadOnly() && (!$indexIds || in_array($index->id(), $indexIds))) {
            \Drupal::state()->set('search_api_solr.' . $index->id() . '.last_update', \Drupal::time()->getRequestTime());
          }
        }
      }
    }

    foreach ($servers as $server) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();
      foreach ($server->getIndexes() as $index) {
        if ($index->status() && !$index->isReadOnly() && (!$indexIds || in_array($index->id(), $indexIds))) {
          $backend->finalizeIndex($index);
        }
      }
    }
  }

  /**
   * Gets search server.
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getServer(string $server_id): ServerInterface {
    $servers = $this->loadServers([$server_id]);
    $server = reset($servers);
    if (!$server) {
      throw new SearchApiSolrException(sprintf('Unknown server %s', $server_id));
    }
    if (!($server->getBackend() instanceof SolrBackendInterface)) {
      throw new SearchApiSolrException(sprintf('Server %s is not a Solr server', $server->label()));
    }

    return $server;
  }

  /**
   * Re-index the index.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   Defines the interface of server entities.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function reindex(ServerInterface $server): void {
    foreach ($server->getIndexes() as $index) {
      if ($index->status() && !$index->isReadOnly()) {
        $index->reindex();
      }
    }
  }

  /**
   * Indexes items on one or more indexes.
   *
   * @param string[]|null $indexIds
   *   (optional) An array of index IDs, or NULL if we should index items for
   *   all enabled indexes.
   * @param int $threads
   *   (optional) The number of parallel threads.
   * @param int|null $batchSize
   *   (optional) The maximum number of items to process per batch, an empty
   *   value to use the default cron limit configured for the index, or a
   *   negative value to index all items in a single batch.
   *
   * @return int[]
   *   The batch IDs.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if an indexing batch process could not be created.
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set.
   */
  public function indexParallelCommand(?array $indexIds = NULL, $threads = 2, $batchSize = NULL): array {
    $indexes = $this->loadIndexes($indexIds);
    if (!$indexes) {
      return [];
    }

    $ids = [];

    /** @var \Drupal\search_api_solr\Entity\Index $index */
    foreach ($indexes as $index) {
      if (!$index->status() || $index->isReadOnly() || !($index->getServerInstance()->getBackend() instanceof SolrBackendInterface)) {
        continue;
      }
      $tracker = $index->getTrackerInstance();
      $indexed = (int) $tracker->getIndexedItemsCount();
      $remaining = $tracker->getTotalItemsCount() - $indexed;

      if (!$remaining) {
        $this->logSuccess($this->t("The index @index is up to date.", ['@index' => $index->label()]));
        continue;
      }
      else {
        $arguments = [
          '@remaining' => $remaining,
          '@index' => $index->label(),
        ];
        $this->logSuccess($this->t("Found @remaining items to index for @index.", $arguments));
      }

      $currentThreads = $threads;
      // Get the batch size to use for this index (in case none was specified in
      // the command).
      $currentBatchSize = $batchSize;
      if (!$currentBatchSize) {
        $cron_limit = $index->getOption('cron_limit');
        $currentBatchSize = $cron_limit ?: \Drupal::configFactory()
          ->get('search_api.settings')
          ->get('default_cron_limit');
      }

      if ($tracker->getPluginId() === 'index_parallel') {
        while ($currentBatchSize * IndexParallel::SAFETY_DISTANCE_FACTOR * $currentThreads >= $remaining) {
          if ($currentThreads === 1) {
            break;
          }
          $currentThreads--;
        }
      }
      else {
        $currentThreads = 1;
      }

      $index->setIndexingEmptyIndex($indexed === 0);

      $arguments = [
        '@index' => $index->label(),
        '@threads' => $currentThreads,
        '@batch_size' => $currentBatchSize,
        '@empty' => $index->isIndexingEmptyIndex() ? $this->t('empty') : $this->t('not empty'),
      ];
      $this->logSuccess($this->t("Indexing parallel with @threads threads (@batch_size items per batch run) for the index '@index'. The index is @empty", $arguments));

      // Create the batch.
      try {
        $ids[$index->id()] = IndexParallelBatchHelper::create($index, $currentBatchSize, $currentThreads);
      } catch (SearchApiException $e) {
        throw new ConsoleException($this->t("Couldn't create all batches, check the batch size and other parameters."), 0, $e);
      }
    }

    // In case that multiple indexes get indexed, it makes sense to distribute
    // the threads across the indexes instead of running multiple threads for
    // the same index. That will distribute the load if one index requires a lot
    // of processing in PHP while another one performs a lot of database or API
    // queries.
    $shuffled_ids = [];
    $max_ids = 0;
    foreach ($ids as $index_ids) {
      $count = count($index_ids);
      if ($count > $max_ids) {
        $max_ids = $count;
      }
    }

    for ($i = 0; $i < $max_ids; ++$i) {
      foreach ($ids as $index_ids) {
        if (isset($index_ids[$i])) {
          $shuffled_ids[] = $index_ids[$i];
        }
      }
    }

    return $shuffled_ids;
  }

  public function resetEmptyIndexState(array $indexIds): void {
    if ($indexes = $this->loadIndexes($indexIds)) {
      /** @var \Drupal\search_api_solr\Entity\Index $index */
      foreach ($indexes as $index) {
        $index->setIndexingEmptyIndex(FALSE);
      }
    }
  }

}

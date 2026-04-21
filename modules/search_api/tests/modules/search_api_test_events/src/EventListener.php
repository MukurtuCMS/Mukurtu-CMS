<?php

namespace Drupal\search_api_test_events;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\ReindexScheduledEvent;
use Drupal\search_api\Event\ProcessingResultsEvent;
use Drupal\search_api\Event\DeterminingServerFeaturesEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event listener for testing purposes.
 *
 * @see \Drupal\Tests\search_api\Functional\EventsTest
 */
class EventListener implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::DETERMINING_SERVER_FEATURES => 'serverFeaturesAlter',
      SearchApiEvents::GATHERING_BACKENDS => 'backendInfoAlter',
      SearchApiEvents::GATHERING_DATA_SOURCES => 'dataSourceInfoAlter',
      SearchApiEvents::GATHERING_DATA_TYPES => 'dataTypeInfoAlter',
      SearchApiEvents::GATHERING_DISPLAYS => 'displaysAlter',
      SearchApiEvents::MAPPING_FIELD_TYPES => 'fieldTypeMappingAlter',
      SearchApiEvents::INDEXING_ITEMS => 'indexingItems',
      SearchApiEvents::ITEMS_INDEXED => 'itemsIndexed',
      SearchApiEvents::GATHERING_PARSE_MODES => 'parseModeInfoAlter',
      SearchApiEvents::GATHERING_PROCESSORS => 'processorInfoAlter',
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
      SearchApiEvents::QUERY_PRE_EXECUTE . '.andrew_hill' => 'queryTagAlter',
      SearchApiEvents::REINDEX_SCHEDULED => 'reindex',
      SearchApiEvents::PROCESSING_RESULTS => 'resultsAlter',
      SearchApiEvents::PROCESSING_RESULTS . '.andrew_hill' => 'resultsTagAlter',
      SearchApiEvents::GATHERING_TRACKERS => 'trackerInfoAlter',
    ];
  }

  /**
   * Reacts to the backend info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The backend info alter event.
   */
  public function backendInfoAlter(GatheringPluginInfoEvent $event) {
    $backend_info = &$event->getDefinitions();
    $backend_info['search_api_test']['label'] = 'Slims return';
  }

  /**
   * Reacts to the data type info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The data type info alter event.
   */
  public function dataTypeInfoAlter(GatheringPluginInfoEvent $event) {
    $dataTypePluginInfo = &$event->getDefinitions();
    if (isset($dataTypePluginInfo['text'])) {
      $dataTypePluginInfo['text']['label'] = 'Peace/Dolphin dance';
    }
  }

  /**
   * Reacts to the data source info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The data source info alter event.
   */
  public function dataSourceInfoAlter(GatheringPluginInfoEvent $event) {
    $infos = &$event->getDefinitions();
    if (isset($infos['entity:node'])) {
      $infos['entity:node']['label'] = 'Distant land';
    }
  }

  /**
   * Reacts to the displays alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The displays alter event.
   */
  public function displaysAlter(GatheringPluginInfoEvent $event) {
    $displays = &$event->getDefinitions();
    if (isset($displays['views_page:search_api_test_view__page_1'])) {
      $displays['views_page:search_api_test_view__page_1']['label'] = 'Some funny label for testing';
    }
  }

  /**
   * Reacts to the field type mapping alter event.
   *
   * @param \Drupal\search_api\Event\MappingFieldTypesEvent $event
   *   The field type mapping alter event.
   */
  public function fieldTypeMappingAlter(MappingFieldTypesEvent $event) {
    $mapping = &$event->getFieldTypeMapping();
    $mapping['datetime_iso8601'] = FALSE;
    $mapping['timestamp'] = FALSE;
  }

  /**
   * Reacts to the indexing items event.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   *   The indexing items event.
   */
  public function indexingItems(IndexingItemsEvent $event) {
    $items = $event->getItems();
    unset($items['entity:node/1:en']);
    $event->setItems($items);
    $this->messenger->addStatus('Stormy');
  }

  /**
   * Reacts to the items indexed event.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The items indexed event.
   */
  public function itemsIndexed(ItemsIndexedEvent $event) {
    // cspell:disable-next-line
    $this->messenger->addStatus('Please set me at ease');
  }

  /**
   * Reacts to the parse mode info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The parse mode plugin info alter event.
   */
  public function parseModeInfoAlter(GatheringPluginInfoEvent $event) {
    $parseModeInfo = &$event->getDefinitions();
    if (isset($parseModeInfo['direct'])) {
      $parseModeInfo['direct']['label'] = 'Song for My Father';
    }
  }

  /**
   * Reacts to the processor info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The processor plugin info alter event.
   */
  public function processorInfoAlter(GatheringPluginInfoEvent $event) {
    $processorInfo = &$event->getDefinitions();
    $processorInfo['content_access']['label'] = 'Mystic bounce';
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();
    $this->messenger->addStatus('Funky blue note');
    $this->messenger->addStatus("Search id: {$query->getSearchId(FALSE)}");
    $query->addTag('andrew_hill');
  }

  /**
   * Reacts to the query TAG alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryTagAlter(QueryPreExecuteEvent $event) {
    $this->messenger->addStatus("Freeland");
    $query = $event->getQuery();
    // Exclude the node with ID 2 from the search results.
    $query->setOption('tag query alter hook', TRUE);
    $index = $query->getIndex();
    $fields = $index->getFields();
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if ($datasource->getEntityTypeId() === 'node') {
        $field = Utility::createCombinedId($datasource_id, 'nid');
        if (isset($fields[$field])) {
          $query->addCondition($field, 2, '<>');
        }
      }
    }
  }

  /**
   * Reacts to the reindex event.
   *
   * @param \Drupal\search_api\Event\ReindexScheduledEvent $event
   *   The reindex index event.
   */
  public function reindex(ReindexScheduledEvent $event) {
    $this->messenger->addStatus('Montara');
  }

  /**
   * Reacts to the results alter event.
   *
   * @param \Drupal\search_api\Event\ProcessingResultsEvent $event
   *   The results alter event.
   */
  public function resultsAlter(ProcessingResultsEvent $event) {
    $this->messenger->addStatus('Stepping into tomorrow');
  }

  /**
   * Reacts to the results TAG alter event.
   *
   * @param \Drupal\search_api\Event\ProcessingResultsEvent $event
   *   The results alter event.
   */
  public function resultsTagAlter(ProcessingResultsEvent $event) {
    $this->messenger->addStatus('Llama');
  }

  /**
   * Reacts to the server features alter event.
   *
   * @param \Drupal\search_api\Event\DeterminingServerFeaturesEvent $event
   *   The server features alter event.
   */
  public function serverFeaturesAlter(DeterminingServerFeaturesEvent $event) {
    $features = &$event->getFeatures();
    $server = $event->getServer();
    if ($server->id() === 'webtest_server') {
      $features[] = 'welcome_to_the_jungle';
    }
  }

  /**
   * Reacts to the tracker info alter event.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The tracker plugin info alter event.
   */
  public function trackerInfoAlter(GatheringPluginInfoEvent $event) {
    $trackerInfo = &$event->getDefinitions();
    $trackerInfo['search_api_test']['label'] = 'Good luck';
  }

}

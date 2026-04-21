<?php

namespace Drupal\search_api_solr_devel\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\devel\DevelDumperManagerInterface;
use Drupal\search_api\Backend\BackendPluginManager;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\tracker\Basic;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_solr\Controller\EventDispatcherTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for devel module routes.
 */
class DevelController extends ControllerBase {

  use EventDispatcherTrait;

  /**
   * The server storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\search_api\Backend\BackendPluginManager
   */
  protected $backendPluginManager;

  /**
   * The Devel dumper manager.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $develDumperManager;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a DevelController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\search_api\Backend\BackendPluginManager $backend_plugin_manager
   *   The backend plugin manager.
   * @param \Drupal\devel\DevelDumperManagerInterface $devel_dumper_manager
   *   The Devel dumper manager.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The Search API Fields Helper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, BackendPluginManager $backend_plugin_manager, DevelDumperManagerInterface $devel_dumper_manager, FieldsHelperInterface $fields_helper, ModuleHandlerInterface $module_handler, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->storage = $entity_type_manager->getStorage('search_api_server');
    $this->backendPluginManager = $backend_plugin_manager;
    $this->develDumperManager = $devel_dumper_manager;
    $this->fieldsHelper = $fields_helper;
    $this->moduleHandler = $module_handler;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.search_api.backend'),
      $container->get('devel.dumper'),
      $container->get('search_api.fields_helper'),
      $container->get('module_handler'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Returns all available Solr backend plugins.
   *
   * @return string[]
   *   An associative array mapping backend plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getBackends() {
    $backends = [];
    $plugin_definitions = $this->backendPluginManager->getDefinitions();
    foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
      if (is_a($plugin_definition['class'], $plugin_definitions['search_api_solr']['class'], TRUE)) {
        $backends[] = $plugin_id;
      }
    }
    return $backends;
  }

  /**
   * Prints the document structure to be indexed by Solr.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return array
   *   Array of page elements to render.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function entitySolr(RouteMatchInterface $route_match) {
    $output_details = [];
    $table_rows = [];
    $num = 0;

    $parameter_name = $route_match->getRouteObject()->getOption('_devel_entity_type_id');
    $entity = $route_match->getParameter($parameter_name);

    if ($entity instanceof ContentEntityInterface) {
      foreach ($this->getBackends() as $backend_id) {
        /** @var \Drupal\search_api\ServerInterface[] $servers */
        $servers = $this->storage->loadByProperties([
          'backend' => $backend_id,
          'status' => TRUE,
        ]);
        foreach ($servers as $server) {
          /** @var \Drupal\search_api\ServerInterface $server */
          /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
          $backend = $server->getBackend();
          $indexes = $server->getIndexes();
          $solr = $backend->getSolrConnector();
          foreach ($indexes as $index) {
            if ($index->status()) {
              foreach ($index->getDatasourceIds() as $datasource_id) {
                $datasource = $index->getDatasource($datasource_id);
                if ($entity->getEntityTypeId() === $datasource->getEntityTypeId()) {
                  foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
                    if (!$entity->hasTranslation($langcode)) {
                      continue;
                    }
                    $item_id = $datasource->getItemId($entity->getTranslation($langcode)->getTypedData());
                    if ($item_id === NULL) {
                      // The entity is not provided by this datasource.
                      continue;
                    }
                    // @todo improve that ID generation?
                    $item_id = $datasource_id . '/' . $item_id;
                    $items = [];
                    $base_summary_row = $this->getBaseRow($server, $index, $datasource_id, $entity, $langcode, $item_id);

                    // @todo Run a timer on this process and report it?
                    $items[$item_id] = $this->fieldsHelper->createItemFromObject($index, $entity->getTranslation($langcode)->getTypedData(), $item_id);
                    // Alter and preprocess the items to indexed.
                    $index->alterIndexedItems($items);
                    $description = 'This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Please use the "search_api.indexing_items" event instead. See https://www.drupal.org/node/3059866';
                    $this->moduleHandler()->alterDeprecated($description, 'search_api_index_items', $this, $items);
                    $event = new IndexingItemsEvent($index, $items);
                    $this->eventDispatcher()->dispatch($event, SearchApiEvents::INDEXING_ITEMS);
                    $items = $event->getItems();
                    $index->preprocessIndexItems($items);

                    // Gather documents generated by Search API.
                    $documents = $backend->getDocuments($index, $items);
                    foreach ($documents as $document) {
                      $summary_row = $base_summary_row;
                      $summary_row['num'] = $num + 1;
                      $fields = $document->getFields();
                      $summary_row['object_size'] = ByteSizeMarkup::create(strlen(json_encode($fields)));
                      ksort($fields);
                      $details_id = $fields['id'];
                      $output_details[$details_id] = [
                        '#type' => 'details',
                        '#title' => $this->t('Row #@num: local and Solr indexing data', ['@num' => $num + 1]),
                      ];

                      $output_details[$details_id][] = [
                        '#markup' => '<h3>' . $this->t('Locally-generated data that would be sent to Solr during indexing:') . '</h3>',
                      ];
                      $output_details[$details_id][] = [
                        '#markup' => $this->develDumperManager->dumpOrExport(
                          $fields,
                          $this->t('Locally-generated data'),
                          TRUE
                        ),
                      ];

                      // Show current data for this item from the Solr backend.
                      /** @var \Drupal\search_api_solr\SolrConnectorInterface $solr */
                      /** @var \Solarium\QueryType\Select\Query\Query $query */
                      $output_details[$details_id][] = [
                        '#markup' => '<h3>' . $this->t('Current data in Solr for this item:') . '</h3>',
                      ];
                      $summary_row['solr_id'] = $fields['id'];
                      $query = $solr->getSelectQuery();
                      $query->setQuery('id:"' . $fields['id'] . '"');
                      $query->setFields('*');
                      try {
                        // @todo Run a timer on this process and report it?
                        /** @var \Solarium\QueryType\Select\Result\Result $results */
                        $results = $solr->execute($query, $backend->getCollectionEndpoint($index));
                        $num_found = $results->getNumFound();
                        $summary_row['solr_exists'] = $this->t('yes');
                      }
                      catch (\Exception $e) {
                        $results = [];
                        $num_found = -1;
                        $output_details[$details_id][] = [
                          '#markup' => $this->t('Error querying the Solr server!') . '<pre>' . $e->getMessage() . '</pre>',
                        ];
                        $summary_row['solr_exists'] = $this->t('error');
                      }

                      // If no item found in Solr, report it.
                      if ($num_found === 0) {
                        $summary_row['solr_exists'] = $this->t('no');
                        $output_details[$details_id][] = [
                          '#markup' => $this->t(
                            'No Solr document found with the ID %id',
                            ['%id' => $fields['id']]
                          ),
                        ];
                      }
                      if ($num_found === 1) {
                        // Show Solr documents for this item.
                        $solr_documents = $results->getDocuments();
                        $fields = $solr_documents[0]->getFields();
                        $summary_row['solr_size'] = ByteSizeMarkup::create(strlen(json_encode($fields)));
                        if (!empty($fields['timestamp'])) {
                          $summary_row['solr_changed'] = $this->showTimeAndTimeAgo(strtotime($fields['timestamp']));
                        }
                        ksort($fields);
                        $output_details[$details_id][] = [
                          '#markup' => $this->develDumperManager->dumpOrExport(
                            $fields,
                            $this->t('Solr data'),
                            TRUE
                          ),
                        ];
                      }

                      $table_rows[$num++] = $summary_row;
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    // Message for no output.
    if (empty($output_details)) {
      return ['#markup' => $this->t('No enabled indexes with a Solr backend that contain this item were found.')];
    }
    return array_merge(
      [
        '#title' => $this->t('Search API Solr devel status'),
        'header' => [
          '#markup' => $this->t('This page shows Search API and Solr indexing data for the current entity.'),
        ],
        'summary_table' => [
          '#type' => 'table',
          '#prefix' => '<h3>' . $this->t('Summary') . '</h3><p>' . $this->t("Each row in the table represents an item generated from this entity according to the Search API index configuration (data sources, fields, processors, hook implementations, etc.). The first columns correspond to the Search API tracking information (kept in the site's database), and the rest of the columns show information about the corresponding Solr document for each item. See the <a href=\"https://www.drupal.org/docs/8/modules/search-api/developer-documentation\">developer documentation</a>.") . '</p>',
          '#header' => $this->summaryTableHeader(),
          '#rows' => $table_rows,
        ],
      ],
      $output_details);
  }

  /**
   * Given a timestamp it returns both a human-readable date + "time ago".
   *
   * @param int $timestamp
   *   A UNIX timestamp to display.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The formatted timestamp.
   */
  protected function showTimeAndTimeAgo($timestamp) {
    return $this->t('%time (%time_ago ago)', [
      '%time' => $this->dateFormatter->format($timestamp),
      '%time_ago' => $this->dateFormatter->formatDiff($timestamp, $this->time->getRequestTime()),
    ]);
  }

  /**
   * Returns header for summary table.
   *
   * @return string[]
   *   An array of table headers.
   */
  protected function summaryTableHeader() {
    return [
      'num' => '#',
      'server' => $this->t('Search API server'),
      'index' => $this->t('Search API index'),
      'id' => $this->t('Item Datasource & ID'),
      'langcode' => $this->t('Item langcode'),
      'tracked' => $this->t('Is item tracked in SAPI Index?'),
      'changed' => $this->t('Last time item was marked to be indexed'),
      'status' => $this->t('Has item been sent to Solr?'),
      'object_size' => $this->t('Local: item size'),
      'solr_id' => $this->t('Solr: document ID'),
      'solr_exists' => $this->t('Solr: document exists?'),
      'solr_changed' => $this->t('Solr: time indexed'),
      'solr_size' => $this->t('Solr: document size'),
    ];
  }

  /**
   * Return a base row for the summary table, which will be modified later on.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The Search API server entity.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   * @param string $datasource_id
   *   The ID of the datasource.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to return the base row.
   * @param string $langcode
   *   The language code.
   * @param string $item_id
   *   The internal item ID of the entity.
   *
   * @return string[]
   *   An associative array of strings for the base row.
   */
  protected function getBaseRow(ServerInterface $server, IndexInterface $index, $datasource_id, EntityInterface $entity, $langcode, $item_id) {
    // Build table row.
    $base_row = [
      'num' => 0,
      'server' => $this->t('<a href=":url">@id</a>', [
        '@id' => $server->id(),
        ':url' => Url::fromRoute(
          'entity.search_api_server.canonical',
          ['search_api_server' => $server->id()]
        )->toString(),
      ]),
      'index' => $this->t(
        '<a href=":index_url">@index_id</a><br />(%read_write_mode mode)',
        [
          '@index_id' => $index->id(),
          ':index_url' => Url::fromRoute('entity.search_api_index.canonical',
            ['search_api_index' => $index->id()])->toString(),
          '%read_write_mode' => $index->isReadOnly() ? $this->t('read-only') : $this->t('read-write'),
        ]),
      'id' => $datasource_id . '/' . $entity->id(),
      'langcode' => $langcode,
      'tracked' => '',
      'changed' => '-',
      'status' => '-',
      'object_size' => '-',
      'solr_id' => '-',
      'solr_exists' => '',
      'solr_changed' => '-',
      'solr_size' => '-',
    ];

    // Fetch tracker information.
    $tracker = $index->getTrackerInstance();
    if ($tracker instanceof Basic) {
      $select = $tracker->getDatabaseConnection()
        ->select('search_api_item', 'sai');
      $select->condition('index_id', $index->id());
      $select->condition('datasource', $datasource_id);
      $select->condition('item_id', $item_id);
      $select->fields('sai', ['item_id', 'status', 'changed']);
      $tracker_data = $select->execute()->fetch();
      // Add tracker information to row.
      if ($tracker_data) {
        $base_row['tracked'] = $this->t('yes');
        $base_row['changed'] = $this->showTimeAndTimeAgo($tracker_data->changed);
        $base_row['status'] = $tracker_data->status ? $this->t('no') : $this->t('yes');
      }
      else {
        $base_row['tracked'] = $this->t('no');
      }
    }
    else {
      $base_row['tracked'] = $this->t('unsupported tracker');
    }

    return $base_row;
  }

}

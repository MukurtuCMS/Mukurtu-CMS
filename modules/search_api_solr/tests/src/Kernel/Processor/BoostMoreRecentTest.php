<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;

/**
 * Tests the "Boost more recent" processor.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api_solr\Plugin\search_api\processor\BoostMoreRecent
 */
class BoostMoreRecentTest extends ProcessorTestBase {

  use SolrBackendTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'search_api_solr',
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('solr_boost_more_recent');
    $this->enableSolrServer();

    // Create a node type for testing.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();

    // Add a datetime field.
    $dateFieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'node',
      'type' => 'datetime',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
    ]);
    $dateFieldStorage->save();

    $rangeField = FieldConfig::create([
      'field_storage' => $dateFieldStorage,
      'bundle' => 'page',
      'required' => TRUE,
    ]);
    $rangeField->save();

    // Create a node.
    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'test title',
      'field_date' => '2016-09-21',
    ];
    $this->nodes[0] = Node::create($values);
    $this->nodes[0]->save();

    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'some title',
      'field_date' => '2020-10-19',
    ];
    $this->nodes[1] = Node::create($values);
    $this->nodes[1]->save();

    $datasources = $this->container->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index, [
        'entity:node',
      ]);
    $this->index->setDatasources($datasources);

    $nid_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'nid',
      'type' => 'integer',
    ];

    $date_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'field_date',
      'type' => 'date',
    ];

    $fieldsHelper = $this->container->get('search_api.fields_helper');

    $this->index->addField($fieldsHelper->createField($this->index, 'nid', $nid_info));
    $this->index->addField($fieldsHelper->createField($this->index, 'field_date', $date_info));

    $this->index->save();

    \Drupal::getContainer()->get('search_api.index_task_manager')->addItemsAll($this->index);
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * Tests boost by recent date queries.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function testBoostMostRecent() {
    $this->indexItems();

    $this->assertArrayHasKey('solr_boost_more_recent', $this->index->getProcessors(), 'Boost more recent processor is added.');

    $query = new Query($this->index);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
    ], array_keys($result->getResultItems()));

    $processor = $this->index->getProcessor('solr_boost_more_recent');
    $configuration = [
      'boosts' => [
        'field_date' => [
          'boost' => 1,
          'resolution' => 'NOW/HOUR',
          'm' => '3.16e-11',
          'a' => 2.0,
          'b' => 0.08,
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->setProcessors(['solr_boost_more_recent' => $processor]);
    $this->index->save();

    $query = new Query($this->index);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/2:en',
      'entity:node/1:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_date' => [
          'boost' => 1,
          'resolution' => 'NOW',
          'm' => '3.16e-11',
          'a' => 1,
          'b' => 1,
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->setProcessors(['solr_boost_more_recent' => $processor]);
    $this->index->save();

    $query = new Query($this->index);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/2:en',
      'entity:node/1:en',
    ], array_keys($result->getResultItems()));

    $configuration = [];
    $processor->setConfiguration($configuration);
    $this->index->setProcessors(['solr_boost_more_recent' => $processor]);
    $this->index->save();

    $query = new Query($this->index);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
    ], array_keys($result->getResultItems()));
  }

}

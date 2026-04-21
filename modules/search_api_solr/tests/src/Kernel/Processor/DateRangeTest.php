<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use Drupal\Tests\search_api\Kernel\ResultsTrait;

/**
 * Tests the "Date range" processor.
 *
 * @group search_api_solr
 * @group not_solr3
 * @group not_solr4
 *
 * @see \Drupal\search_api_solr\Plugin\search_api\processor\DateRange
 */
class DateRangeTest extends ProcessorTestBase {

  use ResultsTrait;
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
    'datetime_range',
    'search_api_solr',
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('solr_date_range');
    $this->enableSolrServer();

    // Create a node type for testing.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();

    // Add a datetime range field.
    $rangeFieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_date_range',
      'entity_type' => 'node',
      'type' => 'daterange',
      'settings' => ['datetime_type' => DateRangeItem::DATETIME_TYPE_DATE],
    ]);
    $rangeFieldStorage->save();

    $rangeField = FieldConfig::create([
      'field_storage' => $rangeFieldStorage,
      'bundle' => 'page',
      'required' => TRUE,
    ]);
    $rangeField->save();

    // Add a datetime ranges field.
    $rangesFieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_date_ranges',
      'entity_type' => 'node',
      'type' => 'daterange',
      'settings' => ['datetime_type' => DateRangeItem::DATETIME_TYPE_DATE],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $rangesFieldStorage->save();

    $rangesField = FieldConfig::create([
      'field_storage' => $rangesFieldStorage,
      'bundle' => 'page',
      'required' => TRUE,
    ]);
    $rangesField->save();

    // Create a node.
    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'test title',
      'field_date_range' => [
        'value' => '2016-09-21',
        'end_value' => '2016-10-21',
      ],
      'field_date_ranges' => [
        [
          'value' => '2015-09-21',
          'end_value' => '2015-10-21',
        ],
        [
          'value' => '2014-09-21',
          'end_value' => '2014-10-21',
        ],
      ],
    ];
    $this->nodes[0] = Node::create($values);
    $this->nodes[0]->save();

    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'some title',
      'field_date_range' => [
        'value' => '2016-10-19',
        'end_value' => '2016-11-21',
      ],
      'field_date_ranges' => [
        [
          'value' => '2015-10-19',
          'end_value' => '2015-11-21',
        ],
        [
          'value' => '2014-10-19',
          'end_value' => '2014-11-21',
        ],
      ],
    ];
    $this->nodes[1] = Node::create($values);
    $this->nodes[1]->save();

    $datasources = $this->container->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index, [
        'entity:node',
      ]);
    $this->index->setDatasources($datasources);

    $date_range_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'field_date_range',
      'type' => 'solr_date_range',
    ];

    $date_ranges_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'field_date_ranges',
      'type' => 'solr_date_range',
    ];

    $fieldsHelper = $this->container->get('search_api.fields_helper');

    $this->index->addField($fieldsHelper->createField($this->index, 'field_date_range', $date_range_info));
    $this->index->addField($fieldsHelper->createField($this->index, 'field_date_ranges', $date_ranges_info));

    $this->index->save();

    \Drupal::getContainer()->get('search_api.index_task_manager')->addItemsAll($this->index);
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * Tests date range queries.
   *
   * @dataProvider dateRangeFieldQueryDataProvider
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function testDateRangeFieldQueries(string $field, string $date1, string $date2) {
    $this->indexItems();

    $query_helper = \Drupal::getContainer()->get('search_api.query_helper');

    $query = $query_helper->createQuery($this->index);
    $result = $query->execute();
    $expected = [
      'node' => [0, 1],
    ];
    $this->assertResults($result, $expected);

    $query = $query_helper->createQuery($this->index);
    $query->addCondition($field, $date1);
    $result = $query->execute();
    $expected = [
      'node' => [1],
    ];
    $this->assertResults($result, $expected);

    $query = $query_helper->createQuery($this->index);
    $query->addCondition($field, $date2);
    $result = $query->execute();
    $expected = [
      'node' => [0, 1],
    ];
    $this->assertResults($result, $expected);
  }

  /**
   * Data provider for testDateRangeFieldQueries method.
   */
  public static function dateRangeFieldQueryDataProvider() {
    return [
      ['field_date_range', '2016-11-12', '2016-10-20'],
      ['field_date_ranges', '2015-11-12', '2015-10-20'],
      ['field_date_ranges', '2014-11-12', '2014-10-20'],
    ];
  }

}

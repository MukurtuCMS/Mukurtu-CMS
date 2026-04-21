<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_db\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_db\DatabaseCompatibility\LocationAwareDatabaseInterface;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests location searches using the Database Search API backend.
 *
 * Based on tests from the search_api_solr module.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class LocationTest extends BackendTestBase {

  /**
   * The ID of the test server.
   *
   * @var string
   */
  protected $serverId = 'database_search_server';

  /**
   * The ID of the test index.
   *
   * @var string
   */
  protected $indexId = 'database_search_index';

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'language',
    'search_api_db_test_location',
    'search_api_test_example_content',
    'search_api_db',
    'search_api_test_db',
    'entity_test',
  ];

  /**
   * Required parts of the setUp() function that are the same for all backends.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['search_api_db', 'search_api_test_db']);

    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    // Create a location field and storage for testing.
    FieldStorageConfig::create([
      'field_name' => 'location',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'location',
      'bundle' => 'item',
    ])->save();

    // Add some test content.
    $this->addTestEntity(1, [
      'name' => 'London',
      'body' => 'London',
      'type' => 'item',
      'location' => 'POINT(-0.076132 51.508530)',
    ]);
    $this->addTestEntity(2, [
      'name' => 'New York',
      'body' => 'New York',
      'type' => 'item',
      'location' => 'POINT(-73.138260 40.792240)',
    ]);
    $this->addTestEntity(3, [
      'name' => 'Brussels',
      'body' => 'Brussels',
      'type' => 'item',
      'location' => 'POINT(4.355607 50.878899)',
    ]);
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')
      ->count()
      ->accessCheck()
      ->execute();
    $this->assertEquals(3, $count, "$count items inserted.");

    // Index location coordinates as location data type.
    $index = Index::load($this->indexId);
    $field = $this->container->get('search_api.fields_helper')
      ->createField($index, 'location', [
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'location',
        'type' => 'location',
      ]);
    $index->addField($field)->save();
    $this->indexItems($this->indexId);
  }

  /**
   * Tests location search functionality.
   *
   * Only works for MySQL at the moment.
   */
  public function testBackend(): void {
    $dbms_compatibility = $this->container->get('search_api_db.database_compatibility');
    if (!($dbms_compatibility instanceof LocationAwareDatabaseInterface)
        || !$dbms_compatibility->isLocationEnabled()) {
      $this->markTestSkipped('Skipping as database lacks spatial features.');
    }

    // Search 500km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
        'radius' => '500',
      ],
    ];
    $query = $this->buildSearch(place_id_sort: FALSE)->sort('location__distance');
    $query->setOption('search_api_location', $location_options);
    $query->setOption('search_api_retrieved_field_values', ['location__distance' => 'location__distance']);
    // Also add a facet, since that used to lead to a PDO exception.
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'and',
    ];
    $query->setOption('search_api_facets', $facets);
    $result = $query->execute();

    $this->assertResults([3, 1], $result, 'Search for 500km from Antwerp ordered by distance');
    $item = $result->getResultItems()['entity:entity_test_mulrev_changed/3:en'];
    $distance = $item->getField('location__distance')->getValues()[0];

    $this->assertEquals(42.526, round($distance, 3), 'The distance is correctly returned');

    // Search between 100km and 6000km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
      ],
    ];
    $query = $this->buildSearch(place_id_sort: FALSE)
      ->addCondition('location', ['100.000', '6000.000'], 'BETWEEN')
      ->sort('location__distance', 'DESC');

    $query->setOption('search_api_location', $location_options);
    // Also add a facet, since that used to lead to a PDO exception.
    $query->setOption('search_api_facets', $facets);
    $result = $query->execute();
    $this->assertResults([2, 1], $result, 'Search between 100 and 6000km from Antwerp ordered by distance descending');
  }

}

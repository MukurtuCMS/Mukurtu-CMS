<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;

/**
 * Tests location searches and distance facets using the Solr search backend.
 *
 * @group search_api_solr
 * @group not_solr3
 * @group not_solr4
 */
class SearchApiSolrLocationTest extends SolrBackendTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'language',
    'search_api_location',
    'search_api_test_example_content',
    'entity_test',
    'geofield',
  ];

  /**
   * Required parts of the setUp() function that are the same for all backends.
   */
  protected function commonSolrBackendSetUp() {
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    // Create a location field and storage for testing.
    FieldStorageConfig::create([
      'field_name' => 'location',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'geofield',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'location',
      'bundle' => 'item',
    ])->save();

    $this->insertExampleContent();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load($this->indexId);

    $location_info = [
      'datasource_id' => 'entity:entity_test_mulrev_changed',
      'property_path' => 'location',
      'type' => 'location',
    ];
    $rpt_info = [
      'datasource_id' => 'entity:entity_test_mulrev_changed',
      'property_path' => 'location',
      'type' => 'rpt',
    ];
    $fieldsHelper = $this->container->get('search_api.fields_helper');

    // Index location coordinates as location data type.
    $index->addField($fieldsHelper->createField($index, 'location', $location_info));

    // Index location coordinates as rpt data type.
    $index->addField($fieldsHelper->createField($index, 'rpt', $rpt_info));

    $index->save();

    /** @var \Drupal\search_api\Entity\Server $server */
    $server = Server::load($this->serverId);

    $config = $server->getBackendConfig();
    $config['retrieve_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $this->indexItems($this->indexId);
  }

  /**
   * {@inheritdoc}
   */
  public function insertExampleContent() {
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
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')->count()->accessCheck()->execute();
    $this->assertEquals(3, $count, "$count items inserted.");
  }

  /**
   * Tests location searches and distance facets.
   */
  public function testBackend() {
    // Regression test.
    // @see https://www.drupal.org/project/search_api_solr/issues/2921774
    $query = $this->buildSearch(NULL, [], NULL, TRUE);
    $query->addCondition('location', NULL, '<>');
    $result = $query->execute();
    $this->assertResults([1, 2, 3], $result, 'Search for all documents having a location');

    // Search 500km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
        'radius' => '500.00000',
      ],
    ];
    /** @var \Drupal\search_api\Query\ResultSet $result */
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('location__distance');

    $query->setOption('search_api_location', $location_options);
    $result = $query->execute();

    $this->assertResults([3, 1], $result, 'Search for 500km from Antwerp ordered by distance');

    /** @var \Drupal\search_api\Item\Item $item */
    $item = $result->getResultItems()['entity:entity_test_mulrev_changed/3:en'];
    $distance = $item->getField('location__distance')->getValues()[0];

    // We get different precisions from Solr 6 and 7. Therefore we treat the
    // decimal as string and compare the first 9 characters.
    $this->assertEquals('42.526337', substr($distance, 0, 9), 'The distance is correctly returned');

    // Search between 100km and 6000km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
      ],
    ];
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->addCondition('location', ['100', '6000'], 'BETWEEN')
      ->sort('location__distance', 'DESC');

    $query->setOption('search_api_location', $location_options);
    $result = $query->execute();

    $this->assertResults([2, 1], $result, 'Search between 100 and 6000km from Antwerp ordered by distance descending');

    $facets_options['location__distance'] = [
      'field' => 'location__distance',
      'limit' => 10,
      'min_count' => 1,
      'missing' => TRUE,
    ];

    // Search 1000.1145km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
        'radius' => '1000.1145',
      ],
    ];
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('location__distance');

    $query->setOption('search_api_location', $location_options);
    $query->setOption('search_api_facets', $facets_options);
    $result = $query->execute();
    $facets = $result->getExtraData('search_api_facets', [])['location__distance'];

    $expected = [
      [
        'filter' => '[0 199.0229]',
        'count' => 1,
      ],
      [
        'filter' => '[200.0229 399.0458]',
        'count' => 1,
      ],
    ];

    $this->assertEquals($expected, $facets, 'The correct location facets are returned');

    $facets_options['location__distance'] = [
      'field' => 'location__distance',
      'limit' => 3,
      'min_count' => 1,
      'missing' => TRUE,
    ];

    // Search between 100.00km and 1000.1145km from Antwerp.
    $location_options = [
      [
        'field' => 'location',
        'lat' => '51.260197',
        'lon' => '4.402771',
        'radius' => '1000.1145',
      ],
    ];

    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->addCondition('location', ['100.00', '1000.1145'], 'BETWEEN')
      ->sort('location__distance');

    $query->setOption('search_api_location', $location_options);
    $query->setOption('search_api_facets', $facets_options);
    $result = $query->execute();

    $facets = $result->getExtraData('search_api_facets', [])['location__distance'];

    $expected = [
      [
        'filter' => '[100 399.03816666667]',
        'count' => 1,
      ],
    ];

    $this->assertEquals($expected, $facets, 'The correct location facets are returned');

    // Tests the RPT data type of SearchApiSolrBackend.
    $query = $this->buildSearch(NULL, [], NULL, FALSE);
    $options = &$query->getOptions();
    $options['search_api_facets']['rpt'] = [
      'field' => 'rpt',
      'limit' => 3,
      'operator' => 'and',
      'min_count' => 1,
      'missing' => FALSE,
    ];
    $options['search_api_rpt']['rpt'] = [
      'field' => 'rpt',
      'geom' => '["-180 -90" TO "180 90"]',
      'gridLevel' => '2',
      'maxCells' => '35554432',
      'distErrPct' => '',
      'distErr' => '',
      'format' => 'ints2D',
    ];
    $result = $query->execute();
    // @codingStandardsIgnoreLine
    $heatmap = [NULL, NULL, NULL, NULL, NULL, NULL, [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], NULL, [0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL];
    $filter = [];
    if (version_compare($this->getSolrVersion(), '7.5', '>=')) {
      $filter = [
        "gridLevel" => 2,
        "columns" => 32,
        "rows" => 32,
        "minX" => -180.0,
        "maxX" => 180.0,
        "minY" => -90.0,
        "maxY" => 90.0,
        "counts_ints2D" => $heatmap,
      ];
    }
    else {
      $filter = [
        "gridLevel",
        2,
        "columns",
        32,
        "rows",
        32,
        "minX",
        -180.0,
        "maxX",
        180.0,
        "minY",
        -90.0,
        "maxY",
        90.0,
        "counts_ints2D",
        $heatmap,
      ];
    }
    $expected = [
      [
        'filter' => $filter,
        'count' => 3,
      ],
    ];

    $facets = $result->getExtraData('search_api_facets', [])['rpt'];
    $this->assertEquals($expected, $facets, 'The correct location facets are returned');

    $query = $this->buildSearch(NULL, [], NULL, FALSE);
    $options = &$query->getOptions();
    $options['search_api_facets']['rpt'] = [
      'field' => 'rpt',
      'limit' => 4,
      'operator' => 'or',
      'min_count' => 1,
      'missing' => FALSE,
    ];
    $options['search_api_rpt']['rpt'] = [
      'field' => 'rpt',
      'geom' => '["-60 -85" TO "130 70"]',
      'gridLevel' => '2',
      'maxCells' => '35554432',
      'distErrPct' => '',
      'distErr' => '',
      'format' => 'ints2D',
    ];
    $result = $query->execute();
    // @codingStandardsIgnoreLine
    $heatmap = [NULL, NULL, NULL, [0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL];
    $filter = [];
    if (version_compare($this->getSolrVersion(), '7.5', '>=')) {
      $filter = [
        "gridLevel" => 2,
        "columns" => 18,
        "rows" => 29,
        "minX" => -67.5,
        "maxX" => 135.0,
        "minY" => -90.0,
        "maxY" => 73.125,
        "counts_ints2D" => $heatmap,
      ];
    }
    else {
      $filter = [
        "gridLevel",
        2,
        "columns",
        18,
        "rows",
        29,
        "minX",
        -67.5,
        "maxX",
        135.0,
        "minY",
        -90.0,
        "maxY",
        73.125,
        "counts_ints2D",
        $heatmap,
      ];
    }
    $expected = [
      [
        'filter' => $filter,
        'count' => 2,
      ],
    ];

    $facets = $result->getExtraData('search_api_facets', [])['rpt'];
    $this->assertEquals($expected, $facets, 'The correct location facets are returned');

    // Test boundary filtering.
    $query = $this->buildSearch()
      ->addCondition('location', ['38,-75', '42,-70'], 'BETWEEN');
    $result = $query->execute();
    $this->assertResults([2], $result, 'Search for NYC by boundary and NYC only');

    $query = $this->buildSearch()
      ->addCondition('location', ['38,-75', '42,-70'], 'NOT BETWEEN');
    $result = $query->execute();
    $this->assertResults([1, 3], $result, 'Search for outside NYC by boundary');
  }

}

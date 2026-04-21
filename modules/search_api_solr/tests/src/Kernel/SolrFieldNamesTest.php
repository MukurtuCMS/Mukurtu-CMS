<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 * @coversDefaultClass \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend
 */
class SolrFieldNamesTest extends KernelTestBase {

  use InvokeMethodTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'user',
    'link',
    'language',
    'search_api',
    'search_api_solr',
    'search_api_solr_test',
    'system',
  ];

  /**
   * @covers ::getSolrFieldNames
   */
  public function testSolrFieldNames() {
    // Multi-value link field.
    $field = FieldStorageConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'user',
      'type' => 'link',
      'cardinality' => FieldStorageConfigInterface::CARDINALITY_UNLIMITED,
    ]);
    $field->save();
    FieldConfig::create([
      'field_storage' => $field,
      'bundle' => 'user',
    ])->save();

    // Single-value text field.
    $field = FieldStorageConfig::create([
      'field_name' => 'field_bio',
      'entity_type' => 'user',
      'type' => 'string_long',
      'cardinality' => 1,
    ]);
    $field->save();
    FieldConfig::create([
      'field_storage' => $field,
      'bundle' => 'user',
    ])->save();

    $index = Index::create([
      'id' => 'index',
      'datasource_settings' => [
        'entity:node' => [
          'plugin_id' => 'entity:node',
          'settings' => [],
        ],
      ],
      'field_settings' => [
        'title' => [
          'label' => 'Link title',
          'type' => 'string',
          'datasource_id' => 'entity:node',
          'property_path' => 'uid:entity:field_links:title',
        ],
        'bio' => [
          'label' => 'Bio field',
          'type' => 'string',
          'datasource_id' => 'entity:node',
          'property_path' => 'uid:entity:field_bio:value',
        ],
      ],
    ]);

    $backend = SearchApiSolrBackend::create($this->container, [], 'test', []);
    $fields = $backend->getSolrFieldNames($index);

    $this->assertSame('sm_title', $fields['title']);
    $this->assertSame('ss_bio', $fields['bio']);

    $fields = $index->getFields();
    $cardinality = $this->invokeMethod($backend, 'getPropertyPathCardinality', [
      $fields['title']->getPropertyPath(),
      $fields['title']->getDatasource()->getPropertyDefinitions(),
    ]);
    $this->assertEquals(FieldStorageConfigInterface::CARDINALITY_UNLIMITED, $cardinality);
    $cardinality = $this->invokeMethod($backend, 'getPropertyPathCardinality', [
      $fields['bio']->getPropertyPath(),
      $fields['bio']->getDatasource()->getPropertyDefinitions(),
    ]);
    $this->assertEquals(1, $cardinality);

    // Test Typed Data.
    $index = Index::create([
      'id' => 'typed_data_index',
      'datasource_settings' => [
        'search_api_solr_test_widget' => [
          'plugin_id' => 'search_api_solr_test_widget',
          'settings' => [],
        ],
      ],
      'field_settings' => [
        'widget_types' => [
          'label' => 'Widget Types',
          'type' => 'string',
          'datasource_id' => 'search_api_solr_test_widget',
          'property_path' => 'widget_types',
        ],
      ],
    ]);

    $backend = SearchApiSolrBackend::create($this->container, [], 'test', []);
    $fields = $backend->getSolrFieldNames($index);

    $this->assertSame('sm_widget_types', $fields['widget_types']);
  }

}

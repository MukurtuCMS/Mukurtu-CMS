<?php

namespace Drupal\Tests\facets\Kernel\Entity;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\facets\FacetSourceInterface;
use Drupal\facets\Plugin\facets\facet_source\SearchApiDisplay;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\facets\Exception\Exception;

/**
 * Class FacetFacetSourceTest.
 *
 * Tests facet source behavior for the facet entity.
 *
 * @group facets
 * @coversDefaultClass \Drupal\facets\Entity\Facet
 */
class FacetFacetSourceTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facets',
    'facets_custom_widget',
    'facets_search_api_dependency',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'search_api_test_example_content',
    'views',
    'rest',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig([
      'search_api_test_example_content',
      'search_api_test_db',
    ]);

    $this->installConfig('facets_search_api_dependency');
  }

  /**
   * Tests facet source behavior for the facet entity.
   *
   * @covers ::getFacetSourceId
   * @covers ::setFacetSourceId
   * @covers ::getFacetSources
   * @covers ::getFacetSource
   * @covers ::getFacetSourceConfig
   */
  public function testFacetSource() {
    $entity = new Facet([], 'facets_facet');
    $this->assertNull($entity->getFacetSourceId());

    // Check that the facet source is in the list of search api displays.
    $displays = $this->container
      ->get('plugin.manager.search_api.display')
      ->getDefinitions();
    $this->assertArrayHasKey('views_page:search_api_test_view__page_1', $displays);

    // Check that has transformed into a facet source as expected.
    $facet_sources = $this->container
      ->get('plugin.manager.facets.facet_source')
      ->getDefinitions();
    $this->assertArrayHasKey('search_api:views_page__search_api_test_view__page_1', $facet_sources);

    // Check the behavior of the facet sources.
    $display_name = 'search_api:views_page__search_api_test_view__page_1';
    $entity->setFacetSourceId($display_name);
    $this->assertEquals($display_name, $entity->getFacetSourceId());
    $this->assertInstanceOf(SearchApiDisplay::class, $entity->getFacetSources()[$display_name]);
    $this->assertInstanceOf(SearchApiDisplay::class, $entity->getFacetSource());
    $this->assertInstanceOf(FacetSourceInterface::class, $entity->getFacetSourceConfig());
    $this->assertEquals($display_name, $entity->getFacetSourceConfig()->getName());
    $this->assertEquals('f', $entity->getFacetSourceConfig()->getFilterKey());
  }

  /**
   * Tests invalid query type.
   *
   * The error here is triggered because no field id is set.
   *
   * @covers ::getQueryType
   * @covers ::getFacetSource
   */
  public function testInvalidQueryType() {
    $entity = new Facet([], 'facets_facet');
    $entity->setWidget('links');
    $entity->setFacetSourceId('search_api:views_page__search_api_test_view__page_1');

    $this->expectException(InvalidQueryTypeException::class);
    $entity->getQueryType();
  }

  /**
   * Tests valid query type.
   *
   * @covers ::getQueryType
   * @covers ::getFacetSource
   */
  public function testQueryType() {
    $entity = new Facet([], 'facets_facet');
    $entity->setWidget('links');
    $entity->setFacetSourceId('search_api:views_page__search_api_test_view__page_1');
    $entity->setFieldIdentifier('name');

    $selectedQueryType = $entity->getQueryType();
    $this->assertEquals('search_api_string', $selectedQueryType);
  }

  /**
   * Tests the selection of a query type.
   *
   * @covers ::getQueryType
   * @covers ::pickQueryType
   */
  public function testQueryTypeJugglingInvalidWidget() {
    $entity = new Facet([], 'facets_facet');
    $entity->setWidget('widget_invalid_qt');
    $entity->setFacetSourceId('search_api:views_page__search_api_test_view__page_1');
    $entity->setFieldIdentifier('name');

    $this->expectException(InvalidQueryTypeException::class);
    $entity->getQueryType();
  }

  /**
   * Tests the selection of a query type.
   *
   * @covers ::getQueryType
   * @covers ::pickQueryType
   */
  public function testQueryTypeJugglingInvalidProcessor() {
    $entity = new Facet([], 'facets_facet');
    $entity->setWidget('links');
    $entity->setFacetSourceId('search_api:views_page__search_api_test_view__page_1');
    $entity->setFieldIdentifier('name');
    $entity->addProcessor([
      'processor_id' => 'invalid_qt',
      'weights' => [],
      'settings' => [],
    ]);

    $this->expectException(InvalidQueryTypeException::class);
    $entity->getQueryType();
  }

  /**
   * Tests the selection of a query type.
   *
   * @covers ::getQueryType
   * @covers ::pickQueryType
   */
  public function testQueryTypeJugglingInvalidCombo() {
    $entity = new Facet([], 'facets_facet');
    $entity->setWidget('widget_date_qt');
    $entity->setFacetSourceId('search_api:views_page__search_api_test_view__page_1');
    $entity->setFieldIdentifier('name');
    $processor = [
      'processor_id' => 'test_pre_query',
      'weights' => [],
      'settings' => [],
    ];
    $entity->addProcessor($processor);

    $this->expectException(InvalidQueryTypeException::class);
    $entity->getQueryType();
  }

  /**
   * Test the data definitions.
   *
   * @covers \Drupal\facets\Plugin\facets\facet_source\SearchApiDisplay::getDataDefinition
   */
  public function testDataDefinitions() {
    // Create and configure facet.
    $entity = new Facet([], 'facets_facet');
    $display_name = 'search_api:views_page__search_api_test_view__page_1';
    $entity->setFacetSourceId($display_name);

    $this->assertInstanceOf(DataDefinitionInterface::class, $entity->getFacetSource()->getDataDefinition('id'));
    $this->assertInstanceOf(DataDefinitionInterface::class, $entity->getFacetSource()->getDataDefinition('name'));
    $this->assertInstanceOf(DataDefinitionInterface::class, $entity->getFacetSource()->getDataDefinition('category'));

    // When trying to get a field that doesn't exist, an error should be thrown.
    $this->expectException(Exception::class);
    $entity->getFacetSource()->getDataDefinition('llama');
  }

}

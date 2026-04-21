<?php

namespace Drupal\Tests\facets\Kernel\FacetManager;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\facets\FacetInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Provides the DefaultFacetManager test.
 *
 * @group facets
 * @coversDefaultClass \Drupal\facets\FacetManager\DefaultFacetManager
 */
class DefaultFacetManagerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facets',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'facets_processors_collection',
    'facets_search_api_dependency',
    'facets_query_processor',
    'system',
    'user',
    'views',
    'rest',
    'serialization',
  ];

  /**
   * Facets entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $facetStorage;

  /**
   * An instance of the "facets.manager" service.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    $state_service = \Drupal::state();
    $state_service->set('search_api_use_tracking_batch', FALSE);

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig([
      'search_api_test_db',
      'facets_search_api_dependency',
    ]);

    $this->facetStorage = $this->entityTypeManager->getStorage('facets_facet');
    $this->facetManager = $this->container->get('facets.manager');
  }

  /**
   * Tests the getEnabledFacets method.
   *
   * @covers ::getEnabledFacets
   */
  public function testGetEnabledFacets() {
    /** @var \Drupal\facets\FacetManager\DefaultFacetManager $dfm */
    $returnValue = $this->facetManager->getEnabledFacets();
    $this->assertEmpty($returnValue);

    // Create a facet.
    $entity = $this->createAndSaveFacet('Mercury', 'planets');

    $returnValue = $this->facetManager->getEnabledFacets();
    $this->assertNotEmpty($returnValue);
    $this->assertSame($entity->id(), $returnValue['Mercury']->id());
  }

  /**
   * Tests the getFacetsByFacetSourceId method.
   *
   * @covers ::getFacetsByFacetSourceId
   */
  public function testGetFacetsByFacetSourceId() {
    $this->assertEmpty($this->facetManager->getFacetsByFacetSourceId('planets'));

    // Create 2 different facets with a unique facet source id.
    $this->createAndSaveFacet('Jupiter', 'planets');
    $this->createAndSaveFacet('Pluto', 'former_planets');

    $planetFacets = $this->facetManager->getFacetsByFacetSourceId('planets');
    $this->assertNotEmpty($planetFacets);
    $this->assertCount(1, $planetFacets);
    $this->assertSame('Jupiter', $planetFacets['Jupiter']->id());

    $formerPlanetFacets = $this->facetManager->getFacetsByFacetSourceId('former_planets');
    $this->assertNotEmpty($formerPlanetFacets);
    $this->assertCount(1, $formerPlanetFacets);
    $this->assertSame('Pluto', $formerPlanetFacets['Pluto']->id());

    // Make pluto a planet again.
    $entity = $this->facetStorage->load('Pluto');
    $entity->setFacetSourceId('planets');
    $this->facetStorage->save($entity);

    // Test that we now hit the static cache.
    $planetFacets = $this->facetManager->getFacetsByFacetSourceId('planets');
    $this->assertNotEmpty($planetFacets);
    $this->assertCount(1, $planetFacets);

    // Change the 'facets' property on the manager to public, so we can
    // overwrite it here. This is because otherwise we run into the static
    // caches.
    $this->resetFacetsManagerStaticCache();

    // Now that the static cache is reset, test that we have 2 planets.
    $planetFacets = $this->facetManager->getFacetsByFacetSourceId('planets');
    $this->assertNotEmpty($planetFacets);
    $this->assertCount(2, $planetFacets);
    $this->assertSame('Jupiter', $planetFacets['Jupiter']->id());
    $this->assertSame('Pluto', $planetFacets['Pluto']->id());
  }

  /**
   * Tests the cachebillity data passed into search query.
   */
  public function testAlterQueryCacheabilityMetadata() {
    // @see facets_processors_collection_facets_search_api_query_type_mapping_alter().
    \Drupal::state()->set('facets_processors_collection_alter_string_query_handler', TRUE);

    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('search_api_test_view')
      ->getExecutable();
    $view->setDisplay('page_1');

    $query = $view->getQuery()->getSearchApiQuery();

    // Create facets for a SAPI view display.
    $facet_source = 'search_api:views_page__search_api_test_view__page_1';
    $facet_mars = $this->createAndSaveFacet('Mars', $facet_source);
    $facet_neptune = $this->createAndSaveFacet('Neptune', $facet_source);
    $expected_tags = array_unique(array_merge(
      $query->getCacheTags(),
      $facet_mars->getCacheTags(),
      $facet_neptune->getCacheTags(),
      [
        'fpc:query_plugin_type_plugin',
        'dummy_query_pre_query_tag',
      ]
    ));
    $this->resetFacetsManagerStaticCache();
    $expected_contexts = array_unique(array_merge(
      $query->getCacheContexts(),
      $facet_mars->getFacetSource()->getCacheContexts(),
      [
        'facets_filter:f',
        'fpc_query_type_plugin',
        'dummy_query_pre_query',
      ]
    ));

    // Make sure that query cachebillity will include facets cache tags e.g.
    // view results will depends on the facet configuration.
    $this->facetManager->alterQuery($query, $facet_source);
    $this->assertEqualsCanonicalizing($expected_contexts, $query->getCacheContexts());
    $this->assertEqualsCanonicalizing($expected_tags, $query->getCacheTags());
  }

  /**
   * Tests the cachebillity data passed into search query.
   *
   * @param string $facet_source_id
   *   The tested facet source ID.
   * @param array $expected_metadata
   *   The expected cache metadata for the given facet source.
   *
   * @dataProvider buildCacheabilityMetadataProvider
   */
  public function testBuildCacheabilityMetadata(string $facet_source_id, array $expected_metadata) {
    $facet = $this->createAndSaveFacet('mars', $facet_source_id);

    $cacheable_processors = [
      'fpc_post_query_processor',
      'fpc_build_processor',
      'fpc_sort_processor',
    ];

    foreach ($cacheable_processors as $processor) {
      $facet->addProcessor([
        'processor_id' => $processor,
        'weights' => [],
        'settings' => [],
      ]);
    }

    $facet->setOnlyVisibleWhenFacetSourceIsVisible(FALSE);
    $this->facetStorage->save($facet);
    // Make sure that new processor is taken into consideration.
    $this->resetFacetsManagerStaticCache();

    $build = [];
    $metadata = CacheableMetadata::createFromObject($facet);
    $metadata->applyTo($build);
    $this->assertEquals($expected_metadata['max-age'], $build['#cache']['max-age']);
    $this->assertEqualsCanonicalizing($expected_metadata['contexts'], $build['#cache']['contexts']);
    $this->assertEqualsCanonicalizing($expected_metadata['tags'], $build['#cache']['tags']);

    $facet->removeProcessor('fpc_sort_processor');
    // Test that un-cacheable plugin kills the cache.
    $facet->addProcessor([
      'processor_id' => 'fpc_sort_random_processor',
      'settings' => [],
      'weights' => [],
    ]);

    $this->facetStorage->save($facet);
    $this->resetFacetsManagerStaticCache();

    $build = [];
    $metadata = CacheableMetadata::createFromObject($facet);
    $metadata->applyTo($build);

    $this->assertEquals(0, $build['#cache']['max-age']);
    $this->assertEqualsCanonicalizing($expected_metadata['contexts'], $build['#cache']['contexts']);
    $this->assertEqualsCanonicalizing($expected_metadata['tags'], $build['#cache']['tags']);
  }

  /**
   * Create and save a facet, for usage in test-scenario's.
   *
   * @param string $id
   *   The id.
   * @param string $source
   *   The source id.
   *
   * @return \Drupal\facets\FacetInterface
   *   The newly created facet.
   */
  protected function createAndSaveFacet(string $id, string $source): FacetInterface {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->create([
      'id' => $id,
      'name' => 'Test facet',
    ]);
    $facet->setWidget('links');
    $facet->setFieldIdentifier('type');
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId($source);

    $facet->addProcessor([
      'processor_id' => 'url_processor_handler',
      'settings' => [],
      'weights' => [],
    ]);

    $this->facetStorage->save($facet);
    // Add dummy processor instead of default, to test its cachebillity.
    $source = $facet->getFacetSourceConfig();
    $source->setUrlProcessor('dummy_query');
    $source->save();

    return $facet;
  }

  /**
   * Reset static facets.manager static cache.
   *
   * @todo discuss whether or not this should be done automatically when facet
   * gets inserted/updated or deleted.
   */
  protected function resetFacetsManagerStaticCache() {
    foreach (['builtFacets', 'facets', 'processedFacets'] as $prop) {
      $facetsProperty = new \ReflectionProperty($this->facetManager, $prop);
      $facetsProperty->setAccessible(TRUE);
      $facetsProperty->setValue($this->facetManager, []);
      $facetsProperty->setAccessible(FALSE);
    }
  }

  /**
   * Data provider for testBuildCacheabilityMetadata().
   *
   * @return array
   *   Array of method call argument arrays for testBuildCacheabilityMetadata().
   *
   * @see ::testBuildCacheabilityMetadata
   */
  public static function buildCacheabilityMetadataProvider() {
    $basic = [
      'contexts' => [
        // Facet API uses Request query params to populate active facets values.
        'url.query_args',
        // Added by build fpc_post_query_processor process plugin.
        'fpc_post_query',
        // Added by build fpc_build_processor process plugin.
        'fpc_build',
        // Added by build fpc_sort_processor process plugin.
        'fpc_sort',
        // Added by Url "dummy_query" url processor.
        'dummy_query_pre_query',
        // Added by views view source plugin.
        'url',
        'languages:language_interface',
        'facets_filter:f',
      ],
      'tags' => [
        // Facet controls query and look & feel of the facet results, so it's
        // config should be present as a cache dependency.
        'config:facets.facet.mars',
        // Added by build fpc_post_query_processor process plugin.
        'fpc:post_query_processor',
        // Added by Url "dummy_query" url processor.
        'dummy_query_pre_query_tag',
        // Added by build fpc_build_processor process plugin.
        'fpc:build_processor',
        // Added by build fpc_sort_processor process plugin.
        'fpc:sort_processor',
        // Added by views view source plugin.
        'config:views.view.search_api_test_view',
        'config:search_api.index.database_search_index',
        'search_api_list:database_search_index',
      ],
    ];
    return [
      // Expected cacheability for the facet with a source with disabled cache.
      [
        'search_api:views_page__search_api_test_view__page_1',
        $basic + ['max-age' => 0],
      ],
      // Expected cacheability for the facet with a source that has TAG cache
      // strategy.
      [
        'search_api:views_page__search_api_test_view__page_2_sapi_tag',
        $basic + ['max-age' => Cache::PERMANENT],
      ],
      // Expected cacheability for the facet with a source that has TIME cache
      // strategy.
      [
        'search_api:views_page__search_api_test_view__page_2_sapi_time',
        $basic + ['max-age' => 518400],
      ],
    ];
  }

}

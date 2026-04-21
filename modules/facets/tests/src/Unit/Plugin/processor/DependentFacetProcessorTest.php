<?php

namespace Drupal\Tests\facets\Unit\Plugin\processor;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Tests\facets\Unit\Drupal10CompatibilityUnitTestCase;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\Plugin\facets\processor\DependentFacetProcessor;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\Result\Result;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Unit test for processor.
 *
 * @group facets
 */
class DependentFacetProcessorTest extends Drupal10CompatibilityUnitTestCase {

  /**
   * An array of results.
   *
   * @var \Drupal\facets\Result\ResultInterface[]
   */
  protected $results;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $facet = new Facet([], 'facets_facet');
    $this->results = [
      new Result($facet, 'snow_owl', 'Snow owl', 2),
      new Result($facet, 'forest_owl', 'Forest owl', 3),
      new Result($facet, 'sand_owl', 'Sand owl', 8),
      new Result($facet, 'church_owl', 'Church owl', 1),
      new Result($facet, 'barn_owl', 'Barn owl', 1),
    ];

    $facet_entity_type = $this->createMock(ConfigEntityType::class);
    $facet_entity_type->method('getConfigPrefix')
      ->willReturn('facets.facet');

    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $entity_type_manager->method('getDefinition')
      ->with('facets_facet')
      ->willReturn($facet_entity_type);

    $processor_plugin_manager = $this->createMock(ProcessorPluginManager::class);
    $processor_plugin_manager->method('getDefinitions')
      ->willReturn([]);

    $event_dispatcher = $this->createMock(EventDispatcher::class);

    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('plugin.manager.facets.processor', $processor_plugin_manager);
    $container->set('event_dispatcher', $event_dispatcher);
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests to no-config case.
   */
  public function testNotConfigured() {
    $facetManager = $this->prophesize(DefaultFacetManager::class)
      ->reveal();
    $etm = $this->prophesize(EntityTypeManagerInterface::class)
      ->reveal();
    $dfp = new DependentFacetProcessor([], 'dependent_facet_processor', [], $facetManager, $etm);

    $facet = new Facet(['id' => 'owl', 'name' => 'øwl'], 'facets_facet');

    $computed = $dfp->build($facet, $this->results);
    $this->assertEquals($computed, $this->results);
  }

  /**
   * Tests the case where no facets are enabled.
   */
  public function testNoEnabledFacets() {
    $facetManager = $this->prophesize(DefaultFacetManager::class)
      ->reveal();
    $etm = $this->prophesize(EntityTypeManagerInterface::class)
      ->reveal();
    $configuration = ['owl' => ['enable' => FALSE, 'condition' => 'not_empty']];
    $dfp = new DependentFacetProcessor($configuration, 'dependent_facet_processor', [], $facetManager, $etm);

    $facet = new Facet(['id' => 'owl', 'name' => 'øwl'], 'facets_facet');

    $computed = $dfp->build($facet, $this->results);
    $this->assertEquals($computed, $this->results);
  }

  /**
   * Tests that facet is not empty.
   *
   * @dataProvider provideNegated
   */
  public function testNotEmpty($negated) {
    $facet = new Facet(['id' => 'owl', 'name' => 'øwl'], 'facets_facet');
    $facet->setActiveItem('snow_owl');

    $facetManager = $this->prophesize(DefaultFacetManager::class);
    $facetManager->returnBuiltFacet($facet)->willReturn($facet);

    $entityStorage = $this->prophesize(EntityStorageInterface::class);
    $entityStorage->load('owl')->willReturn($facet);

    $etm = $this->prophesize(EntityTypeManagerInterface::class);
    $etm->getStorage('facets_facet')->willReturn($entityStorage->reveal());

    $configuration = [
      'owl' => [
        'enable' => TRUE,
        'negate' => $negated,
        'condition' => 'not_empty',
      ],
    ];
    $dfp = new DependentFacetProcessor($configuration, 'dependent_facet_processor', [], $facetManager->reveal(), $etm->reveal());

    $computed = $dfp->build($facet, $this->results);

    if ($negated) {
      $this->assertEquals($computed, []);
    }
    else {
      $this->assertEquals($computed, $this->results);
    }
  }

  /**
   * Provides test cases with data.
   *
   * @return array
   *   An array of test data.
   */
  public static function provideNegated() {
    return [
      'negated' => [TRUE],
      'normal' => [FALSE],
    ];
  }

}

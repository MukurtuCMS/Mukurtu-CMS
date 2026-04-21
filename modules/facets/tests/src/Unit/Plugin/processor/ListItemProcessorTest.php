<?php

namespace Drupal\Tests\facets\Unit\Plugin\processor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetSource\FacetSourcePluginInterface;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\Plugin\facets\processor\ListItemProcessor;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\Result\Result;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\BaseFieldDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Unit test for processor.
 *
 * @group facets
 */
class ListItemProcessorTest extends UnitTestCase {

  /**
   * The processor to be tested.
   *
   * @var \Drupal\facets\Processor\BuildProcessorInterface
   */
  protected $processor;

  /**
   * An array containing the results before the processor has ran.
   *
   * @var \Drupal\facets\Result\Result[]
   */
  protected $results;

  /**
   * The processor plugin manager.
   *
   * @var \Drupal\facets_summary\Processor\ProcessorPluginManager
   */
  protected $processorPluginManager;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $facet = new Facet([], 'facets_facet');
    $this->results = [
      new Result($facet, 1, 1, 10),
      new Result($facet, 2, 2, 5),
      new Result($facet, 3, 3, 15),
    ];

    $config_manager = $this->createMock(ConfigManager::class);

    $entity_field_manager = $this->createMock(EntityFieldManager::class);

    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfo::class);

    // Create a search api based facet source and make the property definition
    // return null.
    $data_definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $data_definition->expects($this->any())
      ->method('getPropertyDefinition')
      ->willReturn(NULL);
    $facet_source = $this->createMock(FacetSourcePluginInterface::class);
    $facet_source->expects($this->any())
      ->method('getDataDefinition')
      ->willReturn($data_definition);
    $facet_source->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);
    $facet_source->expects($this->any())
      ->method('getCacheTags')
      ->willReturn([]);
    $facet_source->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(CacheBackendInterface::CACHE_PERMANENT);

    // Add the plugin manager.
    $pluginManager = $this->createMock(FacetSourcePluginManager::class);
    $pluginManager->expects($this->any())
      ->method('hasDefinition')
      ->willReturn(TRUE);
    $pluginManager->expects($this->any())
      ->method('createInstance')
      ->willReturn($facet_source);

    $this->processor = new ListItemProcessor([], 'list_item', [], $config_manager, $entity_field_manager, $entity_type_bundle_info);

    $facet_entity_type = $this->createMock(ConfigEntityType::class);
    $facet_entity_type->method('getConfigPrefix')
      ->willReturn('facets.facet');

    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $entity_type_manager->method('getDefinition')
      ->with('facets_facet')
      ->willReturn($facet_entity_type);

    $this->processorPluginManager = $this->createMock(ProcessorPluginManager::class);
    $this->processorPluginManager->method('getDefinitions')
      ->willReturn(['list_item' => ['class' => ListItemProcessor::class]]);

    $event_dispatcher = $this->createMock(EventDispatcher::class);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.facets.facet_source', $pluginManager);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('plugin.manager.facets.processor', $this->processorPluginManager);
    $container->set('event_dispatcher', $event_dispatcher);
    \Drupal::setContainer($container);
  }

  /**
   * Tests facet build with field.module field.
   */
  public function testBuildConfigurableField() {
    $module_field = $this->createMock(FieldStorageConfig::class);
    // Return cache field metadata.
    $module_field->expects($this->exactly(1))
      ->method('getCacheContexts')
      ->willReturn([]);
    $module_field->expects($this->exactly(1))
      ->method('getCacheTags')
      ->willReturn(['module_field_tag']);
    $module_field->expects($this->exactly(1))
      ->method('getCacheMaxAge')
      ->willReturn(12345);

    // Make sure that when the processor calls loadConfigEntityByName the field
    // we created here is called.
    $config_manager = $this->createMock(ConfigManager::class);
    $config_manager->expects($this->exactly(2))
      ->method('loadConfigEntityByName')
      ->willReturn($module_field);

    $entity_field_manager = $this->createMock(EntityFieldManager::class);

    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfo::class);

    $processor = new ListItemProcessor([], 'list_item', [], $config_manager, $entity_field_manager, $entity_type_bundle_info);
    $this->processorPluginManager->method('createInstance')
      ->willReturn($processor);

    // Config entity field facet.
    $module_field_facet = new Facet([], 'facets_facet');
    $module_field_facet->setFieldIdentifier('test_facet');
    $module_field_facet->setFacetSourceId('llama_source');
    $module_field_facet->setResults($this->results);
    $module_field_facet->addProcessor([
      'processor_id' => 'list_item',
      'weights' => [],
      'settings' => [],
    ]);

    /** @var \Drupal\facets\Result\Result[] $module_field_results */
    $module_field_results = $processor->build($module_field_facet, $this->results);

    $this->assertCount(3, $module_field_results);
    $this->assertEquals('llama', $module_field_results[0]->getDisplayValue());
    $this->assertEquals('badger', $module_field_results[1]->getDisplayValue());
    $this->assertEquals('kitten', $module_field_results[2]->getDisplayValue());
    $this->assertContains('module_field_tag', $module_field_facet->getCacheTags());
    $this->assertEquals(12345, $module_field_facet->getCacheMaxAge());
  }

  /**
   * Tests facet build with field.module field.
   */
  public function testBuildBundle() {
    $module_field = $this->createMock(FieldStorageConfig::class);
    // Return cache field metadata.
    $module_field->expects($this->exactly(1))
      ->method('getCacheContexts')
      ->willReturn([]);
    $module_field->expects($this->exactly(1))
      ->method('getCacheTags')
      ->willReturn(['module_field_tag']);
    $module_field->expects($this->exactly(1))
      ->method('getCacheMaxAge')
      ->willReturn(54321);

    $config_manager = $this->createMock(ConfigManager::class);
    $config_manager->expects($this->exactly(2))
      ->method('loadConfigEntityByName')
      ->willReturn($module_field);

    $entity_field_manager = $this->createMock(EntityFieldManager::class);

    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfo::class);

    $processor = new ListItemProcessor([], 'list_item', [], $config_manager, $entity_field_manager, $entity_type_bundle_info);
    $this->processorPluginManager->method('createInstance')
      ->willReturn($processor);

    // Config entity field facet.
    $module_field_facet = new Facet([], 'facets_facet');
    $module_field_facet->setFieldIdentifier('test_facet');
    $module_field_facet->setFacetSourceId('llama_source');
    $module_field_facet->setResults($this->results);
    $module_field_facet->addProcessor([
      'processor_id' => 'list_item',
      'weights' => [],
      'settings' => [],
    ]);
    $cache_tags = $module_field_facet->getCacheTags();

    /** @var \Drupal\facets\Result\Result[] $module_field_facet- */
    $module_field_results = $processor->build($module_field_facet, $this->results);

    $this->assertCount(3, $module_field_results);
    $this->assertEquals('llama', $module_field_results[0]->getDisplayValue());
    $this->assertEquals('badger', $module_field_results[1]->getDisplayValue());
    $this->assertEquals('kitten', $module_field_results[2]->getDisplayValue());
    $this->assertSame(array_merge($cache_tags, ['module_field_tag']), $module_field_facet->getCacheTags());
    $this->assertEquals(54321, $module_field_facet->getCacheMaxAge());
  }

  /**
   * Tests facet build with base props.
   */
  public function testBuildBaseField() {
    $config_manager = $this->createMock(ConfigManager::class);

    $base_field = $this->createMock(BaseFieldDefinition::class);
    // Return cache field metadata.
    $base_field->expects($this->exactly(1))
      ->method('getCacheContexts')
      ->willReturn([]);
    $base_field->expects($this->exactly(1))
      ->method('getCacheTags')
      ->willReturn(['base_field_tag']);
    $base_field->expects($this->exactly(1))
      ->method('getCacheMaxAge')
      ->willReturn(1235813);

    $entity_field_manager = $this->createMock(EntityFieldManager::class);
    $entity_field_manager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('node', '')
      ->willReturn([
        'test_facet_baseprop' => $base_field,
      ]);

    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfo::class);

    $processor = new ListItemProcessor([], 'list_item', [], $config_manager, $entity_field_manager, $entity_type_bundle_info);
    $this->processorPluginManager->method('createInstance')
      ->willReturn($processor);

    // Base prop facet.
    $base_prop_facet = new Facet([], 'facets_facet');
    $base_prop_facet->setFieldIdentifier('test_facet_baseprop');
    $base_prop_facet->setFacetSourceId('llama_source');
    $base_prop_facet->setResults($this->results);
    $base_prop_facet->addProcessor([
      'processor_id' => 'list_item',
      'weights' => [],
      'settings' => [],
    ]);
    $cache_tags = $base_prop_facet->getCacheTags();

    /** @var \Drupal\facets\Result\Result[] $base_prop_results */
    $base_prop_results = $processor->build($base_prop_facet, $this->results);

    $this->assertCount(3, $base_prop_results);
    $this->assertEquals('llama', $base_prop_results[0]->getDisplayValue());
    $this->assertEquals('badger', $base_prop_results[1]->getDisplayValue());
    $this->assertEquals('kitten', $base_prop_results[2]->getDisplayValue());
    $this->assertSame(array_merge($cache_tags, ['base_field_tag']), $base_prop_facet->getCacheTags());
    $this->assertEquals(1235813, $base_prop_facet->getCacheMaxAge());
  }

  /**
   * Tests configuration.
   */
  public function testConfiguration() {
    $config = $this->processor->defaultConfiguration();
    $this->assertEquals([], $config);
  }

  /**
   * Tests testDescription().
   */
  public function testDescription() {
    $this->assertEquals('', $this->processor->getDescription());
  }

  /**
   * Tests isHidden().
   */
  public function testIsHidden() {
    $this->assertEquals(FALSE, $this->processor->isHidden());
  }

  /**
   * Tests isLocked().
   */
  public function testIsLocked() {
    $this->assertEquals(FALSE, $this->processor->isLocked());
  }

}

namespace Drupal\facets\Plugin\facets\processor;

if (!function_exists('options_allowed_values')) {

  /**
   * Overwrite the global function with a version that returns the test values.
   */
  function options_allowed_values($definition, $entity = NULL) {
    return [
      1 => 'llama',
      2 => 'badger',
      3 => 'kitten',
    ];
  }

}

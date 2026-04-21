<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Plugin\views\argument_default\CurrentContext;
use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPages Views argument default plugin.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Plugin\views\argument_default\CurrentContext
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesViewsPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'views',
    'config_pages',
  ];

  /**
   * The views plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager
   */
  protected $argumentDefaultManager;

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system', 'views']);

    $this->argumentDefaultManager = $this->container->get('plugin.manager.views.argument_default');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_views_type',
      'label' => 'Test Views Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();
  }

  /**
   * Tests that the plugin is discovered.
   */
  public function testPluginDiscovery(): void {
    $definitions = $this->argumentDefaultManager->getDefinitions();

    $this->assertArrayHasKey('config_pages_current_context', $definitions);
  }

  /**
   * Tests plugin definition.
   */
  public function testPluginDefinition(): void {
    $definition = $this->argumentDefaultManager->getDefinition('config_pages_current_context');

    $this->assertIsArray($definition);
    $this->assertEquals('config_pages_current_context', $definition['id']);
  }

  /**
   * Tests defineOptions returns correct structure.
   *
   * @covers ::defineOptions
   */
  public function testDefineOptions(): void {
    $plugin = $this->argumentDefaultManager->createInstance('config_pages_current_context');

    // Access protected method via reflection.
    $reflection = new \ReflectionClass($plugin);
    $method = $reflection->getMethod('defineOptions');
    $method->setAccessible(TRUE);
    $options = $method->invoke($plugin);

    $this->assertArrayHasKey('config_page_type', $options);
    $this->assertEquals('', $options['config_page_type']['default']);
  }

  /**
   * Tests getCacheMaxAge returns PERMANENT.
   *
   * @covers ::getCacheMaxAge
   */
  public function testGetCacheMaxAge(): void {
    $plugin = $this->argumentDefaultManager->createInstance('config_pages_current_context');

    $this->assertEquals(Cache::PERMANENT, $plugin->getCacheMaxAge());
  }

  /**
   * Tests getCacheContexts returns empty array.
   *
   * @covers ::getCacheContexts
   */
  public function testGetCacheContexts(): void {
    $plugin = $this->argumentDefaultManager->createInstance('config_pages_current_context');

    $contexts = $plugin->getCacheContexts();

    $this->assertIsArray($contexts);
    $this->assertEmpty($contexts);
  }

  /**
   * Tests plugin can be instantiated.
   */
  public function testPluginCanBeInstantiated(): void {
    $plugin = $this->argumentDefaultManager->createInstance('config_pages_current_context');

    $this->assertInstanceOf(CurrentContext::class, $plugin);
  }

  /**
   * Tests plugin options structure.
   */
  public function testPluginOptionsStructure(): void {
    $plugin = $this->argumentDefaultManager->createInstance('config_pages_current_context');

    // Set options manually as Views would do.
    $plugin->options['config_page_type'] = 'test_views_type';

    // Access the option.
    $this->assertEquals('test_views_type', $plugin->options['config_page_type']);
  }

}

<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\ConfigPagesContextBase;
use Drupal\config_pages\ConfigPagesContextInterface;
use Drupal\config_pages\ConfigPagesContextManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPages Context System.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'config_pages',
  ];

  /**
   * The context manager.
   *
   * @var \Drupal\config_pages\ConfigPagesContextManager
   */
  protected ConfigPagesContextManager $contextManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);

    $this->contextManager = $this->container->get('plugin.manager.config_pages_context');
  }

  /**
   * Tests that context manager is registered as a service.
   */
  public function testContextManagerIsRegistered(): void {
    $this->assertInstanceOf(ConfigPagesContextManager::class, $this->contextManager);
  }

  /**
   * Tests that context manager can get definitions.
   */
  public function testContextManagerGetDefinitions(): void {
    $definitions = $this->contextManager->getDefinitions();

    $this->assertIsArray($definitions);
  }

  /**
   * Tests that language context plugin is discovered.
   */
  public function testLanguageContextPluginDiscovery(): void {
    $definitions = $this->contextManager->getDefinitions();

    $this->assertArrayHasKey('language', $definitions);
  }

  /**
   * Tests language context plugin definition.
   */
  public function testLanguageContextPluginDefinition(): void {
    $definition = $this->contextManager->getDefinition('language');

    $this->assertIsArray($definition);
    $this->assertEquals('language', $definition['id']);
    $this->assertArrayHasKey('label', $definition);
  }

  /**
   * Tests creating language context plugin instance.
   */
  public function testCreateLanguageContextPlugin(): void {
    $plugin = $this->contextManager->createInstance('language');

    $this->assertInstanceOf(ConfigPagesContextInterface::class, $plugin);
    $this->assertInstanceOf(ConfigPagesContextBase::class, $plugin);
  }

  /**
   * Tests language context getValue returns language ID.
   */
  public function testLanguageContextGetValue(): void {
    $plugin = $this->contextManager->createInstance('language');

    $value = $plugin->getValue();

    $this->assertIsString($value);
    // Default language is 'en'.
    $this->assertEquals('en', $value);
  }

  /**
   * Tests language context getLabel returns language name.
   */
  public function testLanguageContextGetLabel(): void {
    $plugin = $this->contextManager->createInstance('language');

    $label = $plugin->getLabel();

    $this->assertIsString($label);
    // Default language is English.
    $this->assertEquals('English', $label);
  }

  /**
   * Tests language context getLinks returns links array.
   */
  public function testLanguageContextGetLinks(): void {
    $plugin = $this->contextManager->createInstance('language');

    $links = $plugin->getLinks();

    $this->assertIsArray($links);
    // Should have at least one language (English).
    $this->assertNotEmpty($links);
  }

  /**
   * Tests language context links structure.
   */
  public function testLanguageContextLinksStructure(): void {
    $plugin = $this->contextManager->createInstance('language');

    $links = $plugin->getLinks();

    foreach ($links as $link) {
      $this->assertArrayHasKey('title', $link);
      $this->assertArrayHasKey('href', $link);
      $this->assertArrayHasKey('selected', $link);
      $this->assertArrayHasKey('value', $link);
    }
  }

  /**
   * Tests that current language is marked as selected.
   */
  public function testLanguageContextCurrentLanguageSelected(): void {
    $plugin = $this->contextManager->createInstance('language');

    $links = $plugin->getLinks();
    $currentValue = $plugin->getValue();

    $selectedFound = FALSE;
    foreach ($links as $link) {
      if ($link['value'] === $currentValue) {
        $this->assertTrue($link['selected']);
        $selectedFound = TRUE;
      }
    }

    $this->assertTrue($selectedFound, 'Current language should be marked as selected.');
  }

  /**
   * Tests context base class getValue returns empty string.
   */
  public function testContextBaseGetValueReturnsEmptyString(): void {
    // Create a mock plugin using base class directly.
    $plugin = new ConfigPagesContextBase(
      [],
      'test_plugin',
      ['label' => 'Test Plugin']
    );

    $value = $plugin->getValue();

    $this->assertEquals('', $value);
  }

  /**
   * Tests context base class getLabel returns plugin definition label.
   */
  public function testContextBaseGetLabel(): void {
    $plugin = new ConfigPagesContextBase(
      [],
      'test_plugin',
      ['label' => 'Test Label']
    );

    $label = $plugin->getLabel();

    $this->assertEquals('Test Label', $label);
  }

  /**
   * Tests context base class getLinks returns empty array.
   */
  public function testContextBaseGetLinksReturnsEmptyArray(): void {
    $plugin = new ConfigPagesContextBase(
      [],
      'test_plugin',
      ['label' => 'Test Plugin']
    );

    $links = $plugin->getLinks();

    $this->assertIsArray($links);
    $this->assertEmpty($links);
  }

  /**
   * Tests context manager hasDefinition.
   */
  public function testContextManagerHasDefinition(): void {
    $this->assertTrue($this->contextManager->hasDefinition('language'));
    $this->assertFalse($this->contextManager->hasDefinition('non_existing_context'));
  }

  /**
   * Tests that plugin implements correct interface.
   */
  public function testPluginImplementsInterface(): void {
    $plugin = $this->contextManager->createInstance('language');

    $this->assertInstanceOf(ConfigPagesContextInterface::class, $plugin);
  }

  /**
   * Tests context manager can be used multiple times.
   */
  public function testContextManagerMultipleInstances(): void {
    $plugin1 = $this->contextManager->createInstance('language');
    $plugin2 = $this->contextManager->createInstance('language');

    // Both should work independently.
    $this->assertEquals($plugin1->getValue(), $plugin2->getValue());
    $this->assertEquals($plugin1->getLabel(), $plugin2->getLabel());
  }

}

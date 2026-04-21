<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\ConfigPagesViewsData;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesViewsData.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\ConfigPagesViewsData
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesViewsDataTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system', 'views']);
  }

  /**
   * Tests that views data handler is registered.
   */
  public function testViewsDataHandlerRegistered(): void {
    $entityType = $this->container->get('entity_type.manager')
      ->getDefinition('config_pages');

    $handlersClass = $entityType->getHandlerClass('views_data');

    $this->assertEquals(ConfigPagesViewsData::class, $handlersClass);
  }

  /**
   * Tests getViewsData returns correct structure.
   *
   * @covers ::getViewsData
   */
  public function testGetViewsDataReturnsCorrectStructure(): void {
    $viewsData = $this->container->get('views.views_data');

    $data = $viewsData->get('config_pages');

    $this->assertIsArray($data);
    $this->assertArrayHasKey('table', $data);
  }

  /**
   * Tests that table base is defined.
   *
   * @covers ::getViewsData
   */
  public function testTableBaseIsDefined(): void {
    $viewsData = $this->container->get('views.views_data');

    $data = $viewsData->get('config_pages');

    $this->assertArrayHasKey('base', $data['table']);
    $this->assertEquals('id', $data['table']['base']['field']);
  }

  /**
   * Tests table base title.
   *
   * @covers ::getViewsData
   */
  public function testTableBaseTitle(): void {
    $viewsData = $this->container->get('views.views_data');

    $data = $viewsData->get('config_pages');

    $this->assertArrayHasKey('title', $data['table']['base']);
    $this->assertEquals('Config pages', (string) $data['table']['base']['title']);
  }

  /**
   * Tests table base help text.
   *
   * @covers ::getViewsData
   */
  public function testTableBaseHelp(): void {
    $viewsData = $this->container->get('views.views_data');

    $data = $viewsData->get('config_pages');

    $this->assertArrayHasKey('help', $data['table']['base']);
  }

  /**
   * Tests that views data includes entity fields.
   */
  public function testViewsDataIncludesFields(): void {
    $viewsData = $this->container->get('views.views_data');

    $data = $viewsData->get('config_pages');

    // Should include base entity fields.
    $this->assertArrayHasKey('id', $data);
    $this->assertArrayHasKey('label', $data);
    $this->assertArrayHasKey('type', $data);
  }

}

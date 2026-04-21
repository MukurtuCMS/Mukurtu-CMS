<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for config_pages_menu_links_discovered_alter().
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesMenuLinksAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
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
    $this->installConfig(['field', 'system']);

    // Ensure the .module file is loaded.
    \Drupal::moduleHandler()->loadInclude('config_pages', 'module');
  }

  /**
   * Tests menu link is generated for a type with menu path.
   */
  public function testMenuLinkGeneratedForTypeWithPath(): void {
    ConfigPagesType::create([
      'id' => 'test_menu',
      'label' => 'Test Menu Page',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test-menu-page',
        'weight' => 5,
        'description' => 'Test menu description',
      ],
      'token' => FALSE,
    ])->save();

    $links = [];
    config_pages_menu_links_discovered_alter($links);

    $this->assertArrayHasKey('config_pages.test_menu', $links);

    $link = $links['config_pages.test_menu'];
    $this->assertEquals('Test Menu Page', $link['title']);
    $this->assertEquals('Test menu description', $link['description']);
    $this->assertEquals('config_pages.test_menu', $link['route_name']);
    $this->assertTrue($link['enabled']);
    $this->assertEquals(5, $link['weight']);
    $this->assertEquals('config_pages', $link['provider']);
  }

  /**
   * Tests menu link defaults for empty description and weight.
   */
  public function testMenuLinkDefaultValues(): void {
    ConfigPagesType::create([
      'id' => 'test_defaults',
      'label' => 'Test Defaults',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test-defaults',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $links = [];
    config_pages_menu_links_discovered_alter($links);

    $link = $links['config_pages.test_defaults'];
    $this->assertEquals('', $link['description']);
    $this->assertEquals(0, $link['weight']);
  }

  /**
   * Tests multiple types generate multiple menu links.
   */
  public function testMultipleTypesGenerateMultipleLinks(): void {
    ConfigPagesType::create([
      'id' => 'type_a',
      'label' => 'Type A',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/type-a',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    ConfigPagesType::create([
      'id' => 'type_b',
      'label' => 'Type B',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/type-b',
        'weight' => 10,
        'description' => 'B description',
      ],
      'token' => FALSE,
    ])->save();

    $links = [];
    config_pages_menu_links_discovered_alter($links);

    $this->assertArrayHasKey('config_pages.type_a', $links);
    $this->assertArrayHasKey('config_pages.type_b', $links);
    $this->assertEquals('Type A', $links['config_pages.type_a']['title']);
    $this->assertEquals('Type B', $links['config_pages.type_b']['title']);
  }

  /**
   * Tests existing links are preserved.
   */
  public function testExistingLinksPreserved(): void {
    ConfigPagesType::create([
      'id' => 'test_preserve',
      'label' => 'Test Preserve',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test-preserve',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $links = [
      'existing.link' => [
        'title' => 'Existing',
        'route_name' => 'existing.route',
      ],
    ];
    config_pages_menu_links_discovered_alter($links);

    $this->assertArrayHasKey('existing.link', $links);
    $this->assertArrayHasKey('config_pages.test_preserve', $links);
  }

  /**
   * Tests no links generated when no types exist.
   */
  public function testNoLinksWhenNoTypes(): void {
    $links = [];
    config_pages_menu_links_discovered_alter($links);

    $this->assertEmpty($links);
  }

}

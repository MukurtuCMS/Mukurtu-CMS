<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesType entity.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Entity\ConfigPagesType
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesTypeEntityTest extends KernelTestBase {

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
  }

  /**
   * Tests creating a config page type.
   */
  public function testCreateConfigPageType(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'test_type',
      'label' => 'Test Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/test-path',
        'weight' => 5,
        'description' => 'Test description',
      ],
      'token' => TRUE,
    ]);

    $this->assertInstanceOf(ConfigPagesType::class, $configPageType);
    $this->assertEquals('test_type', $configPageType->id());
    $this->assertEquals('Test Type', $configPageType->label());
    $this->assertTrue($configPageType->isNew());
  }

  /**
   * Tests saving and loading a config page type.
   */
  public function testSaveAndLoadConfigPageType(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'test_type',
      'label' => 'Test Type',
      'context' => [
        'show_warning' => TRUE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/test-path',
        'weight' => 10,
        'description' => 'Description',
      ],
      'token' => TRUE,
    ]);
    $configPageType->save();

    $loaded = ConfigPagesType::load('test_type');
    $this->assertNotNull($loaded);
    $this->assertEquals('Test Type', $loaded->label());
    $this->assertEquals('/test-path', $loaded->menu['path']);
    $this->assertEquals(10, $loaded->menu['weight']);
    $this->assertTrue((bool) $loaded->token);
    $this->assertTrue((bool) $loaded->context['show_warning']);
  }

  /**
   * Tests getContextData without any context groups.
   *
   * @covers ::getContextData
   */
  public function testGetContextDataWithoutGroups(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'no_context_type',
      'label' => 'No Context Type',
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
    $configPageType->save();

    $contextData = $configPageType->getContextData();
    $this->assertEquals(serialize([]), $contextData);
  }

  /**
   * Tests getContextData with disabled context groups.
   *
   * @covers ::getContextData
   */
  public function testGetContextDataWithDisabledGroups(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'disabled_context_type',
      'label' => 'Disabled Context Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [
          'some_context' => 0,
          'another_context' => FALSE,
        ],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    $contextData = $configPageType->getContextData();
    $this->assertEquals(serialize([]), $contextData);
  }

  /**
   * Tests getContextLabel without context groups.
   *
   * @covers ::getContextLabel
   */
  public function testGetContextLabelWithoutGroups(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'no_label_type',
      'label' => 'No Label Type',
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
    $configPageType->save();

    $label = $configPageType->getContextLabel();
    $this->assertEquals('', $label);
  }

  /**
   * Tests getContextLinks without context groups.
   *
   * @covers ::getContextLinks
   */
  public function testGetContextLinksWithoutGroups(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'no_links_type',
      'label' => 'No Links Type',
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
    $configPageType->save();

    $links = $configPageType->getContextLinks();
    $this->assertIsArray($links);
    $this->assertEmpty($links);
  }

  /**
   * Tests postDelete removes related config pages.
   *
   * @covers ::postDelete
   */
  public function testPostDeleteRemovesConfigPages(): void {
    // Create a config page type.
    $configPageType = ConfigPagesType::create([
      'id' => 'delete_test_type',
      'label' => 'Delete Test Type',
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
    $configPageType->save();

    // Create config pages of this type.
    $configPage1 = ConfigPages::create([
      'type' => 'delete_test_type',
      'label' => 'Page 1',
      'context' => serialize([]),
    ]);
    $configPage1->save();
    $id1 = $configPage1->id();

    $configPage2 = ConfigPages::create([
      'type' => 'delete_test_type',
      'label' => 'Page 2',
      'context' => serialize([['ctx' => 'val']]),
    ]);
    $configPage2->save();
    $id2 = $configPage2->id();

    // Verify config pages exist.
    $this->assertNotNull(ConfigPages::load($id1));
    $this->assertNotNull(ConfigPages::load($id2));

    // Delete the type.
    $configPageType->delete();

    // Config pages should be deleted too.
    $this->assertNull(ConfigPages::load($id1));
    $this->assertNull(ConfigPages::load($id2));
  }

  /**
   * Tests that type can be deleted when no config pages exist.
   *
   * @covers ::postDelete
   */
  public function testPostDeleteWithNoConfigPages(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'empty_type',
      'label' => 'Empty Type',
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
    $configPageType->save();

    // Should not throw exception.
    $configPageType->delete();

    $this->assertNull(ConfigPagesType::load('empty_type'));
  }

  /**
   * Tests menu configuration.
   */
  public function testMenuConfiguration(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'menu_type',
      'label' => 'Menu Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/custom-path',
        'weight' => -10,
        'description' => 'Custom menu description',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    $loaded = ConfigPagesType::load('menu_type');
    $this->assertEquals('/custom-path', $loaded->menu['path']);
    $this->assertEquals(-10, $loaded->menu['weight']);
    $this->assertEquals('Custom menu description', $loaded->menu['description']);
  }

  /**
   * Tests updating a config page type.
   */
  public function testUpdateConfigPageType(): void {
    $configPageType = ConfigPagesType::create([
      'id' => 'update_type',
      'label' => 'Original Label',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/original',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    // Update the type.
    $configPageType->set('label', 'Updated Label');
    $configPageType->menu['path'] = '/updated';
    $configPageType->token = TRUE;
    $configPageType->save();

    // Reload and verify.
    $loaded = ConfigPagesType::load('update_type');
    $this->assertEquals('Updated Label', $loaded->label());
    $this->assertEquals('/updated', $loaded->menu['path']);
    $this->assertTrue((bool) $loaded->token);
  }

  /**
   * Tests token configuration.
   */
  public function testTokenConfiguration(): void {
    // With token enabled.
    $withToken = ConfigPagesType::create([
      'id' => 'with_token',
      'label' => 'With Token',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => TRUE,
    ]);
    $withToken->save();

    // With token disabled.
    $withoutToken = ConfigPagesType::create([
      'id' => 'without_token',
      'label' => 'Without Token',
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
    $withoutToken->save();

    $this->assertTrue((bool) ConfigPagesType::load('with_token')->token);
    $this->assertFalse((bool) ConfigPagesType::load('without_token')->token);
  }

  /**
   * Tests context show_warning configuration.
   */
  public function testContextShowWarning(): void {
    $withWarning = ConfigPagesType::create([
      'id' => 'warning_on',
      'label' => 'Warning On',
      'context' => [
        'show_warning' => TRUE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $withWarning->save();

    $withoutWarning = ConfigPagesType::create([
      'id' => 'warning_off',
      'label' => 'Warning Off',
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
    $withoutWarning->save();

    $this->assertTrue((bool) ConfigPagesType::load('warning_on')->context['show_warning']);
    $this->assertFalse((bool) ConfigPagesType::load('warning_off')->context['show_warning']);
  }

  /**
   * Tests loading multiple config page types.
   */
  public function testLoadMultipleTypes(): void {
    ConfigPagesType::create([
      'id' => 'type_a',
      'label' => 'Type A',
      'context' => ['show_warning' => FALSE, 'group' => []],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ])->save();

    ConfigPagesType::create([
      'id' => 'type_b',
      'label' => 'Type B',
      'context' => ['show_warning' => FALSE, 'group' => []],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ])->save();

    $types = ConfigPagesType::loadMultiple(['type_a', 'type_b']);
    $this->assertCount(2, $types);
    $this->assertArrayHasKey('type_a', $types);
    $this->assertArrayHasKey('type_b', $types);
  }

}

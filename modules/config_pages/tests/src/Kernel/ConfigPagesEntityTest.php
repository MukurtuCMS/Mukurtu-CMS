<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPages entity.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Entity\ConfigPages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesEntityTest extends KernelTestBase {

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
    $this->installConfig(['field', 'system']);

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_entity_type',
      'label' => 'Test Entity Type',
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
   * Tests creating a config page entity.
   *
   * @covers ::create
   */
  public function testCreateConfigPage(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);

    $this->assertInstanceOf(ConfigPages::class, $configPage);
    $this->assertEquals('test_entity_type', $configPage->bundle());
    $this->assertEquals('Test Config Page', $configPage->label());
    $this->assertTrue($configPage->isNew());
  }

  /**
   * Tests saving and loading a config page.
   *
   * @covers ::create
   */
  public function testSaveAndLoadConfigPage(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $this->assertNotNull($configPage->id());
    $this->assertFalse($configPage->isNew());

    // Load the entity.
    $loaded = ConfigPages::load($configPage->id());
    $this->assertNotNull($loaded);
    $this->assertEquals($configPage->id(), $loaded->id());
    $this->assertEquals('Test Config Page', $loaded->label());
  }

  /**
   * Tests the createDuplicate method.
   *
   * @covers ::createDuplicate
   */
  public function testCreateDuplicate(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Original Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $duplicate = $configPage->createDuplicate();

    $this->assertNull($duplicate->id());
    $this->assertTrue($duplicate->isNew());
    $this->assertEquals('Original Config Page', $duplicate->label());
    $this->assertEquals('test_entity_type', $duplicate->bundle());
  }

  /**
   * Tests theme getter and setter.
   *
   * @covers ::setTheme
   * @covers ::getTheme
   */
  public function testThemeProperty(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'context' => serialize([]),
    ]);

    $this->assertNull($configPage->getTheme());

    $result = $configPage->setTheme('stark');
    $this->assertSame($configPage, $result);
    $this->assertEquals('stark', $configPage->getTheme());
  }

  /**
   * Tests the setLabel method.
   *
   * @covers ::setLabel
   */
  public function testSetLabel(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Initial Label',
      'context' => serialize([]),
    ]);

    $result = $configPage->setLabel('Updated Label');
    $this->assertSame($configPage, $result);
    $this->assertEquals('Updated Label', $configPage->label());
  }

  /**
   * Tests the getChangedTime method.
   *
   * @covers ::getChangedTime
   */
  public function testGetChangedTime(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $changedTime = $configPage->getChangedTime();
    $this->assertIsNumeric($changedTime);
    $this->assertGreaterThan(0, $changedTime);
  }

  /**
   * Tests the static config method.
   *
   * @covers ::config
   */
  public function testConfigMethod(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Load using the config method.
    $loaded = ConfigPages::config('test_entity_type');
    $this->assertNotNull($loaded);
    $this->assertEquals($configPage->id(), $loaded->id());
  }

  /**
   * Tests config method with non-existing type.
   *
   * @covers ::config
   */
  public function testConfigMethodNonExistingType(): void {
    $result = ConfigPages::config('nonexistent_type');
    $this->assertNull($result);
  }

  /**
   * Tests config method with empty type.
   *
   * This test covers the Canvas compatibility fix where config_page_type
   * is empty causing "Call to a member function getContextData() on null".
   *
   * @covers ::config
   */
  public function testConfigMethodWithEmptyType(): void {
    $result = ConfigPages::config('');
    $this->assertNull($result);
  }

  /**
   * Tests config method with null type.
   *
   * @covers ::config
   */
  public function testConfigMethodWithNullType(): void {
    $result = ConfigPages::config(NULL);
    $this->assertNull($result);
  }

  /**
   * Tests config method with explicit context.
   *
   * @covers ::config
   */
  public function testConfigMethodWithContext(): void {
    $context = serialize([['test_context' => 'value1']]);

    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Context Config Page',
      'context' => $context,
    ]);
    $configPage->save();

    // Load with explicit context.
    $loaded = ConfigPages::config('test_entity_type', $context);
    $this->assertNotNull($loaded);
    $this->assertEquals($configPage->id(), $loaded->id());

    // Different context should not find it.
    $differentContext = serialize([['test_context' => 'different_value']]);
    $notFound = ConfigPages::config('test_entity_type', $differentContext);
    $this->assertNull($notFound);
  }

  /**
   * Tests base field definitions.
   *
   * @covers ::baseFieldDefinitions
   */
  public function testBaseFieldDefinitions(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('config_pages');
    $fields = ConfigPages::baseFieldDefinitions($entityType);

    $this->assertArrayHasKey('id', $fields);
    $this->assertArrayHasKey('uuid', $fields);
    $this->assertArrayHasKey('label', $fields);
    $this->assertArrayHasKey('type', $fields);
    $this->assertArrayHasKey('context', $fields);
    $this->assertArrayHasKey('changed', $fields);

    // Check id field is read-only.
    $this->assertTrue($fields['id']->isReadOnly());

    // Check uuid field is read-only.
    $this->assertTrue($fields['uuid']->isReadOnly());
  }

  /**
   * Tests the toUrl method without custom menu path.
   *
   * @covers ::toUrl
   */
  public function testToUrlWithoutMenuPath(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $url = $configPage->toUrl();
    $this->assertStringContainsString('config_pages', $url->getRouteName());
  }

  /**
   * Tests the toUrl method with custom menu path.
   *
   * @covers ::toUrl
   */
  public function testToUrlWithMenuPath(): void {
    // Create type with menu path.
    $configPageTypeWithMenu = ConfigPagesType::create([
      'id' => 'menu_path_type',
      'label' => 'Menu Path Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/custom-menu-path',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $configPageTypeWithMenu->save();

    // Rebuild routes to register the custom path.
    $this->container->get('router.builder')->rebuild();

    $configPage = ConfigPages::create([
      'type' => 'menu_path_type',
      'label' => 'Config Page with Menu',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $url = $configPage->toUrl();
    $this->assertEquals('config_pages.menu_path_type', $url->getRouteName());
  }

  /**
   * Tests updating existing config page.
   */
  public function testUpdateConfigPage(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Original Label',
      'context' => serialize([]),
    ]);
    $configPage->save();
    $originalId = $configPage->id();

    // Update the entity.
    $configPage->setLabel('Updated Label');
    $configPage->save();

    // ID should remain the same.
    $this->assertEquals($originalId, $configPage->id());

    // Reload and verify.
    $loaded = ConfigPages::load($originalId);
    $this->assertEquals('Updated Label', $loaded->label());
  }

  /**
   * Tests deleting a config page.
   */
  public function testDeleteConfigPage(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'To Be Deleted',
      'context' => serialize([]),
    ]);
    $configPage->save();
    $id = $configPage->id();

    // Delete the entity.
    $configPage->delete();

    // Should not be loadable anymore.
    $loaded = ConfigPages::load($id);
    $this->assertNull($loaded);
  }

  /**
   * Tests multiple config pages of the same type with different contexts.
   */
  public function testMultipleConfigPagesWithDifferentContexts(): void {
    $context1 = serialize([['ctx' => 'value1']]);
    $context2 = serialize([['ctx' => 'value2']]);

    $configPage1 = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Config Page 1',
      'context' => $context1,
    ]);
    $configPage1->save();

    $configPage2 = ConfigPages::create([
      'type' => 'test_entity_type',
      'label' => 'Config Page 2',
      'context' => $context2,
    ]);
    $configPage2->save();

    // Load by context.
    $loaded1 = ConfigPages::config('test_entity_type', $context1);
    $loaded2 = ConfigPages::config('test_entity_type', $context2);

    $this->assertEquals($configPage1->id(), $loaded1->id());
    $this->assertEquals($configPage2->id(), $loaded2->id());
    $this->assertNotEquals($loaded1->id(), $loaded2->id());
  }

}

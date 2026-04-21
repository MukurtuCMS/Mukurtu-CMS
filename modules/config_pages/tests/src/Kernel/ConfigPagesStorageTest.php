<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesStorage.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\ConfigPagesStorage
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesStorageTest extends KernelTestBase {

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
   * The config pages storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * A saved config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

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
      'id' => 'test_storage_type',
      'label' => 'Test Storage Type',
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

    // Create a config page entity.
    $this->configPage = ConfigPages::create([
      'type' => 'test_storage_type',
      'label' => 'Test Storage Page',
      'context' => serialize([]),
    ]);
    $this->configPage->save();

    $this->storage = $this->container->get('entity_type.manager')
      ->getStorage('config_pages');
  }

  /**
   * Tests loading a config page by numeric ID.
   *
   * @covers ::load
   */
  public function testLoadByNumericId(): void {
    $loaded = $this->storage->load($this->configPage->id());

    $this->assertNotNull($loaded);
    $this->assertInstanceOf(ConfigPages::class, $loaded);
    $this->assertEquals($this->configPage->id(), $loaded->id());
    $this->assertEquals('Test Storage Page', $loaded->label());
  }

  /**
   * Tests loading a config page by type machine name.
   *
   * @covers ::load
   */
  public function testLoadByTypeName(): void {
    $loaded = $this->storage->load('test_storage_type');

    $this->assertNotNull($loaded);
    $this->assertInstanceOf(ConfigPages::class, $loaded);
    $this->assertEquals($this->configPage->id(), $loaded->id());
  }

  /**
   * Tests loading returns NULL for non-existing numeric ID.
   *
   * @covers ::load
   */
  public function testLoadNonExistingNumericId(): void {
    $loaded = $this->storage->load(99999);

    $this->assertNull($loaded);
  }

  /**
   * Tests loading returns NULL for non-existing type name.
   *
   * @covers ::load
   */
  public function testLoadNonExistingTypeName(): void {
    $loaded = $this->storage->load('nonexistent_type');

    $this->assertNull($loaded);
  }

  /**
   * Tests loadMultiple with numeric IDs.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleByNumericIds(): void {
    // Create a second config page type and entity.
    $type2 = ConfigPagesType::create([
      'id' => 'test_storage_type_2',
      'label' => 'Test Storage Type 2',
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
    $type2->save();

    $configPage2 = ConfigPages::create([
      'type' => 'test_storage_type_2',
      'label' => 'Test Storage Page 2',
      'context' => serialize([]),
    ]);
    $configPage2->save();

    $entities = $this->storage->loadMultiple([
      $this->configPage->id(),
      $configPage2->id(),
    ]);

    $this->assertCount(2, $entities);
    $this->assertArrayHasKey($this->configPage->id(), $entities);
    $this->assertArrayHasKey($configPage2->id(), $entities);
  }

  /**
   * Tests loadMultiple with type names.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleByTypeNames(): void {
    $type2 = ConfigPagesType::create([
      'id' => 'test_storage_type_2',
      'label' => 'Test Storage Type 2',
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
    $type2->save();

    $configPage2 = ConfigPages::create([
      'type' => 'test_storage_type_2',
      'label' => 'Test Storage Page 2',
      'context' => serialize([]),
    ]);
    $configPage2->save();

    $entities = $this->storage->loadMultiple([
      'test_storage_type',
      'test_storage_type_2',
    ]);

    $this->assertCount(2, $entities);
  }

  /**
   * Tests loadMultiple with NULL loads all entities.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleWithNull(): void {
    $entities = $this->storage->loadMultiple(NULL);

    $this->assertNotEmpty($entities);
    $this->assertArrayHasKey($this->configPage->id(), $entities);
  }

  /**
   * Tests loadMultiple with empty array loads all entities.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleWithEmptyArray(): void {
    $entities = $this->storage->loadMultiple([]);

    $this->assertNotEmpty($entities);
    $this->assertArrayHasKey($this->configPage->id(), $entities);
  }

  /**
   * Tests loadMultiple skips non-existing entries.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleSkipsNonExisting(): void {
    $entities = $this->storage->loadMultiple([
      $this->configPage->id(),
      99999,
      'nonexistent_type',
    ]);

    $this->assertCount(1, $entities);
    $this->assertArrayHasKey($this->configPage->id(), $entities);
  }

  /**
   * Tests loadMultiple with mixed numeric IDs and type names.
   *
   * @covers ::loadMultiple
   */
  public function testLoadMultipleMixed(): void {
    $type2 = ConfigPagesType::create([
      'id' => 'test_storage_type_2',
      'label' => 'Test Storage Type 2',
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
    $type2->save();

    $configPage2 = ConfigPages::create([
      'type' => 'test_storage_type_2',
      'label' => 'Test Storage Page 2',
      'context' => serialize([]),
    ]);
    $configPage2->save();

    // Mix numeric ID with type name.
    $entities = $this->storage->loadMultiple([
      $this->configPage->id(),
      'test_storage_type_2',
    ]);

    $this->assertCount(2, $entities);
  }

  /**
   * Tests that load by type name returns correct entity for current context.
   *
   * @covers ::load
   */
  public function testLoadByTypeNameRespectsContext(): void {
    $loaded = $this->storage->load('test_storage_type');

    $this->assertNotNull($loaded);
    $this->assertEquals('test_storage_type', $loaded->bundle());
  }

}

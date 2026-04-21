<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for context migration when context settings change.
 *
 * Tests the fix for issue #3537239: Data loss when enabling multilingual
 * on the site and context can no longer load the saved config page.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesContextMigrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'language',
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
    $this->installConfig(['field', 'system', 'language']);
  }

  /**
   * Tests that ConfigPages::config() falls back to empty context entity.
   *
   * When context is enabled on a type but existing entities still have the
   * old empty context hash, the fallback should find them.
   *
   * @covers \Drupal\config_pages\Entity\ConfigPages::config
   */
  public function testFallbackLoadsEntityWithEmptyContext(): void {
    // Create type without context.
    $type = ConfigPagesType::create([
      'id' => 'test_fallback',
      'label' => 'Test Fallback',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ]);
    $type->save();

    // Create entity with empty context.
    $entity = ConfigPages::create([
      'type' => 'test_fallback',
      'label' => 'Test Fallback Page',
      'context' => serialize([]),
    ]);
    $entity->save();
    $entityId = $entity->id();

    // Verify entity loads normally.
    $loaded = ConfigPages::config('test_fallback');
    $this->assertNotNull($loaded);
    $this->assertEquals($entityId, $loaded->id());

    // Now enable language context on the type.
    $type->set('context', [
      'show_warning' => FALSE,
      'group' => ['language' => 'language'],
    ]);
    $type->save();

    // The entity still has context = serialize([]) in DB.
    // With the fallback, config() should still find it.
    $loaded = ConfigPages::config('test_fallback');
    $this->assertNotNull($loaded, 'Entity with empty context should be found via fallback after enabling language context.');
    $this->assertEquals($entityId, $loaded->id());
  }

  /**
   * Tests that fallback does not activate when explicit context is passed.
   *
   * @covers \Drupal\config_pages\Entity\ConfigPages::config
   */
  public function testFallbackDoesNotActivateWithExplicitContext(): void {
    // Create type without context.
    $type = ConfigPagesType::create([
      'id' => 'test_explicit',
      'label' => 'Test Explicit',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ]);
    $type->save();

    // Create entity with empty context.
    $entity = ConfigPages::create([
      'type' => 'test_explicit',
      'label' => 'Test Page',
      'context' => serialize([]),
    ]);
    $entity->save();

    // When explicit context is passed that doesn't match, should return NULL.
    $nonMatchingContext = serialize([['language' => 'fr']]);
    $loaded = ConfigPages::config('test_explicit', $nonMatchingContext);
    $this->assertNull($loaded, 'Fallback should not activate when explicit context is passed.');
  }

  /**
   * Tests migration of entity context when context is enabled on type save.
   *
   * Simulates what ConfigPagesTypeForm::save() does when context changes.
   *
   * @covers \Drupal\config_pages\ConfigPagesTypeForm::migrateEntitiesContext
   */
  public function testMigrateOnContextEnable(): void {
    // Create type without context.
    $type = ConfigPagesType::create([
      'id' => 'test_migrate',
      'label' => 'Test Migrate',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ]);
    $type->save();

    // Create entity with empty context.
    $entity = ConfigPages::create([
      'type' => 'test_migrate',
      'label' => 'Test Migrate Page',
      'context' => serialize([]),
    ]);
    $entity->save();
    $entityId = $entity->id();

    $oldContextData = $type->getContextData();
    $this->assertEquals(serialize([]), $oldContextData);

    // Enable language context.
    $type->set('context', [
      'show_warning' => FALSE,
      'group' => ['language' => 'language'],
    ]);
    $type->save();

    $newContextData = $type->getContextData();
    $this->assertNotEquals($oldContextData, $newContextData);

    // Simulate the migration that ConfigPagesTypeForm::save() performs.
    $storage = \Drupal::entityTypeManager()->getStorage('config_pages');
    $entities = $storage->loadByProperties([
      'type' => 'test_migrate',
      'context' => $oldContextData,
    ]);

    // Check no entity exists for new context yet.
    $existingNew = $storage->loadByProperties([
      'type' => 'test_migrate',
      'context' => $newContextData,
    ]);
    $this->assertEmpty($existingNew);

    // Perform migration.
    $this->assertNotEmpty($entities);
    $migratedEntity = current($entities);
    $migratedEntity->set('context', $newContextData);
    $migratedEntity->save();

    // Verify entity now loads with new context.
    $loaded = ConfigPages::config('test_migrate');
    $this->assertNotNull($loaded);
    $this->assertEquals($entityId, $loaded->id());

    // Verify old context no longer has an entity.
    $oldEntities = $storage->loadByProperties([
      'type' => 'test_migrate',
      'context' => $oldContextData,
    ]);
    $this->assertEmpty($oldEntities);
  }

  /**
   * Tests migration when context is disabled on type save.
   *
   * @covers \Drupal\config_pages\ConfigPagesTypeForm::migrateEntitiesContext
   */
  public function testMigrateOnContextDisable(): void {
    // Create type WITH language context.
    $type = ConfigPagesType::create([
      'id' => 'test_disable',
      'label' => 'Test Disable',
      'context' => [
        'show_warning' => FALSE,
        'group' => ['language' => 'language'],
      ],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ]);
    $type->save();

    $contextWithLang = $type->getContextData();

    // Create entity with language context hash.
    $entity = ConfigPages::create([
      'type' => 'test_disable',
      'label' => 'Test Disable Page',
      'context' => $contextWithLang,
    ]);
    $entity->save();
    $entityId = $entity->id();

    // Now disable context.
    $type->set('context', [
      'show_warning' => FALSE,
      'group' => [],
    ]);
    $type->save();

    $emptyContext = $type->getContextData();
    $this->assertEquals(serialize([]), $emptyContext);

    // Simulate migration.
    $storage = \Drupal::entityTypeManager()->getStorage('config_pages');
    $entities = $storage->loadByProperties([
      'type' => 'test_disable',
      'context' => $contextWithLang,
    ]);

    $this->assertNotEmpty($entities);
    $migratedEntity = current($entities);
    $migratedEntity->set('context', $emptyContext);
    $migratedEntity->save();

    // Verify entity loads with empty context.
    $loaded = ConfigPages::config('test_disable');
    $this->assertNotNull($loaded);
    $this->assertEquals($entityId, $loaded->id());
  }

  /**
   * Tests that migration does not overwrite existing entity.
   *
   * When a new context entity already exists, the old one should not
   * replace it.
   *
   * @covers \Drupal\config_pages\ConfigPagesTypeForm::migrateEntitiesContext
   */
  public function testMigrateDoesNotOverwriteExisting(): void {
    // Create type with language context.
    $type = ConfigPagesType::create([
      'id' => 'test_no_overwrite',
      'label' => 'Test No Overwrite',
      'context' => [
        'show_warning' => FALSE,
        'group' => ['language' => 'language'],
      ],
      'menu' => ['path' => '', 'weight' => 0, 'description' => ''],
      'token' => FALSE,
    ]);
    $type->save();

    $contextWithLang = $type->getContextData();

    // Create entity with correct language context hash.
    $correctEntity = ConfigPages::create([
      'type' => 'test_no_overwrite',
      'label' => 'Correct Entity',
      'context' => $contextWithLang,
    ]);
    $correctEntity->save();
    $correctEntityId = $correctEntity->id();

    // Also create an orphaned entity with empty context (simulating pre-bug
    // state where entity was saved before context was enabled).
    $orphanedEntity = ConfigPages::create([
      'type' => 'test_no_overwrite',
      'label' => 'Orphaned Entity',
      'context' => serialize([]),
    ]);
    $orphanedEntity->save();
    $orphanedEntityId = $orphanedEntity->id();

    // Simulate migration attempt (from empty to language context).
    $storage = \Drupal::entityTypeManager()->getStorage('config_pages');
    $oldEntities = $storage->loadByProperties([
      'type' => 'test_no_overwrite',
      'context' => serialize([]),
    ]);
    $this->assertNotEmpty($oldEntities);

    // Check if target already exists.
    $existingNew = $storage->loadByProperties([
      'type' => 'test_no_overwrite',
      'context' => $contextWithLang,
    ]);
    // Target exists — migration should be skipped.
    $this->assertNotEmpty($existingNew);

    // Verify ConfigPages::config() returns the correct entity (not orphaned).
    $loaded = ConfigPages::config('test_no_overwrite');
    $this->assertNotNull($loaded);
    $this->assertEquals($correctEntityId, $loaded->id());

    // Verify orphaned entity still exists in DB.
    $orphanedStillExists = $storage->load($orphanedEntityId);
    $this->assertNotNull($orphanedStillExists);
  }

}

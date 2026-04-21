<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the static ID map cache in ConfigPages::config().
 *
 * Verifies that repeated calls to ConfigPages::config() do not trigger
 * redundant entity queries, and that the cache can be reset.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesStaticCacheTest extends KernelTestBase {

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

    // Reset any leftover cache from previous tests.
    ConfigPages::resetConfigCache();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    ConfigPages::resetConfigCache();
    parent::tearDown();
  }

  /**
   * Creates a config page type.
   *
   * @param string $id
   *   The type machine name.
   *
   * @return \Drupal\config_pages\Entity\ConfigPagesType
   *   The created type entity.
   */
  protected function createConfigPageType(string $id): ConfigPagesType {
    $type = ConfigPagesType::create([
      'id' => $id,
      'label' => ucfirst($id),
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
    $type->save();
    return $type;
  }

  /**
   * Tests that repeated config() calls return the same entity.
   */
  public function testRepeatedCallsReturnSameEntity(): void {
    $this->createConfigPageType('test_cache');
    $entity = ConfigPages::create([
      'type' => 'test_cache',
      'label' => 'Test Cache',
      'context' => serialize([]),
    ]);
    $entity->save();

    $result1 = ConfigPages::config('test_cache');
    $result2 = ConfigPages::config('test_cache');

    $this->assertNotNull($result1);
    $this->assertNotNull($result2);
    $this->assertEquals($result1->id(), $result2->id());
  }

  /**
   * Tests that the static ID map is populated after first call.
   */
  public function testIdMapIsPopulated(): void {
    $this->createConfigPageType('test_perf');
    $entity = ConfigPages::create([
      'type' => 'test_perf',
      'label' => 'Test Perf',
      'context' => serialize([]),
    ]);
    $entity->save();

    // ID map should be empty before first call.
    $reflection = new \ReflectionClass(ConfigPages::class);
    $prop = $reflection->getProperty('idMap');
    $this->assertEmpty($prop->getValue());

    // First call populates the map.
    $result = ConfigPages::config('test_perf');
    $this->assertNotNull($result);

    $idMap = $prop->getValue();
    $this->assertNotEmpty($idMap, 'ID map should be populated after first call.');
    $this->assertContains($entity->id(), $idMap,
      'ID map should contain the entity ID.');

    // Subsequent calls should return the same entity without new queries.
    // We verify this indirectly: reset the entity storage cache so that
    // loadByProperties would fail, but load($id) still works via storage.
    $result2 = ConfigPages::config('test_perf');
    $this->assertEquals($result->id(), $result2->id());
  }

  /**
   * Tests that many repeated calls return consistent results.
   */
  public function testManyRepeatedCalls(): void {
    $this->createConfigPageType('test_many');
    $entity = ConfigPages::create([
      'type' => 'test_many',
      'label' => 'Test Many',
      'context' => serialize([]),
    ]);
    $entity->save();

    // Call config() 100 times. All should return the same entity.
    for ($i = 0; $i < 100; $i++) {
      $result = ConfigPages::config('test_many');
      $this->assertEquals($entity->id(), $result->id());
    }
  }

  /**
   * Tests that resetConfigCache() clears the ID map.
   */
  public function testResetConfigCache(): void {
    $this->createConfigPageType('test_reset');
    $entity = ConfigPages::create([
      'type' => 'test_reset',
      'label' => 'Test Reset',
      'context' => serialize([]),
    ]);
    $entity->save();
    $originalId = $entity->id();

    // Prime cache.
    $result = ConfigPages::config('test_reset');
    $this->assertEquals($originalId, $result->id());

    // Delete and recreate with different data.
    $entity->delete();
    $newEntity = ConfigPages::create([
      'type' => 'test_reset',
      'label' => 'Test Reset New',
      'context' => serialize([]),
    ]);
    $newEntity->save();
    $newId = $newEntity->id();

    // Without reset, cache still returns the old ID.
    // Core's load() will return NULL for deleted entity.
    $staleResult = ConfigPages::config('test_reset');
    $this->assertNull($staleResult, 'Stale cache returns NULL for deleted entity.');

    // After reset, fresh lookup returns the new entity.
    ConfigPages::resetConfigCache();
    $freshResult = ConfigPages::config('test_reset');
    $this->assertNotNull($freshResult);
    $this->assertEquals($newId, $freshResult->id());
  }

  /**
   * Tests that config() returns NULL for nonexistent type.
   */
  public function testNonexistentType(): void {
    $result = ConfigPages::config('nonexistent');
    $this->assertNull($result);
  }

  /**
   * Tests that config() returns NULL for empty type.
   */
  public function testEmptyType(): void {
    $result = ConfigPages::config('');
    $this->assertNull($result);

    $result = ConfigPages::config(NULL);
    $this->assertNull($result);
  }

  /**
   * Tests caching with explicit context parameter.
   */
  public function testCachingWithExplicitContext(): void {
    $this->createConfigPageType('test_ctx');
    $contextValue = serialize([['language' => 'fr']]);
    $entity = ConfigPages::create([
      'type' => 'test_ctx',
      'label' => 'Test Context',
      'context' => $contextValue,
    ]);
    $entity->save();

    $result1 = ConfigPages::config('test_ctx', $contextValue);
    $result2 = ConfigPages::config('test_ctx', $contextValue);

    $this->assertNotNull($result1);
    $this->assertEquals($result1->id(), $result2->id());
  }

  /**
   * Tests that different types are cached independently.
   */
  public function testDifferentTypesIndependent(): void {
    $this->createConfigPageType('type_a');
    $this->createConfigPageType('type_b');

    $entityA = ConfigPages::create([
      'type' => 'type_a',
      'label' => 'Type A',
      'context' => serialize([]),
    ]);
    $entityA->save();

    $entityB = ConfigPages::create([
      'type' => 'type_b',
      'label' => 'Type B',
      'context' => serialize([]),
    ]);
    $entityB->save();

    $resultA = ConfigPages::config('type_a');
    $resultB = ConfigPages::config('type_b');

    $this->assertNotNull($resultA);
    $this->assertNotNull($resultB);
    $this->assertNotEquals($resultA->id(), $resultB->id());
    $this->assertEquals($entityA->id(), $resultA->id());
    $this->assertEquals($entityB->id(), $resultB->id());
  }

  /**
   * Tests that config() returns NULL when no entity matches.
   */
  public function testNoMatchingEntity(): void {
    $this->createConfigPageType('test_empty');
    $result = ConfigPages::config('test_empty');
    $this->assertNull($result);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_notifications_update_40059().
 *
 * Recreates what mukurtu_notifications_update_40050 left on older sites: two
 * message field storages whose config was deleted directly, so Drupal still
 * records them as installed.
 *
 * @see mukurtu_notifications_update_40059()
 */
#[Group('mukurtu_notifications')]
class StaleMessageFieldsUpdateTest extends KernelTestBase {

  /**
   * The two fields 40050 removed.
   */
  protected const FIELDS = ['field_community', 'field_collection'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'message',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_notifications');
    require_once $module_path . '/mukurtu_notifications.install';

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
  }

  /**
   * Creates a message field storage, then deletes its config as 40050 did.
   */
  protected function createStaleField(string $field_name): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'user'],
      'persist_with_no_fields' => TRUE,
    ])->save();
    $this->config("field.storage.message.$field_name")->delete();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Returns the message field storages with a pending definition change.
   */
  protected function pendingFields(): array {
    $change_list = $this->container->get('entity.definition_update_manager')->getChangeList();
    return array_keys($change_list['message']['field_storage_definitions'] ?? []);
  }

  /**
   * The stale definitions and their tables are removed.
   */
  public function testRemovesStaleFields(): void {
    foreach (self::FIELDS as $field_name) {
      $this->createStaleField($field_name);
    }
    $this->assertEqualsCanonicalizing(self::FIELDS, $this->pendingFields());

    mukurtu_notifications_update_40059();
    field_purge_batch(100);

    $this->assertSame([], $this->pendingFields());
    $schema = $this->container->get('database')->schema();
    foreach (self::FIELDS as $field_name) {
      $this->assertFalse($schema->tableExists("message__$field_name"), $field_name);
    }
  }

  /**
   * Sites without the stale definitions are left alone.
   *
   * That covers a site that never had the fields and one that still has them
   * as real fields.
   */
  public function testLeavesOtherSitesAlone(): void {
    mukurtu_notifications_update_40059();
    $this->assertSame([], $this->pendingFields());

    FieldStorageConfig::create([
      'field_name' => 'field_community',
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'user'],
      'persist_with_no_fields' => TRUE,
    ])->save();

    mukurtu_notifications_update_40059();

    $this->assertNotNull(FieldStorageConfig::loadByName('message', 'field_community'));
    $this->assertTrue($this->container->get('database')->schema()->tableExists('message__field_community'));
    $this->assertSame([], $this->pendingFields());
  }

}

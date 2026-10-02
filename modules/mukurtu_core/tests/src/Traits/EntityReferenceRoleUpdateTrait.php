<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Traits;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Helpers for testing the entity_reference to reference-with-role update.
 *
 * A Kernel test installs the current code, so its fields already have the
 * role column. rollBackToEntityReference() puts one field back into the
 * state an existing site is in before mukurtu_core_update_40206().
 */
trait EntityReferenceRoleUpdateTrait {

  /**
   * Restores a field's installed definition and tables to entity_reference.
   */
  protected function rollBackToEntityReference(string $entity_type_id, string $field_name): void {
    $last_installed = $this->container->get('entity.last_installed_schema.repository');
    $current = $last_installed->getLastInstalledFieldStorageDefinitions($entity_type_id)[$field_name];
    $this->assertSame('mukurtu_entity_reference_role', $current->getType());

    $settings = $current->getSettings();
    unset($settings['role_target_bundles']);
    $old = BaseFieldDefinition::create('entity_reference')
      ->setName($field_name)
      ->setTargetEntityTypeId($entity_type_id)
      ->setProvider($current->getProvider())
      ->setSettings($settings)
      ->setCardinality($current->getCardinality())
      ->setRevisionable($current->isRevisionable())
      ->setTranslatable($current->isTranslatable());

    $table_mapping = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->getTableMapping();
    $column = $table_mapping->getFieldColumnName($current, 'role_target_id');
    $tables = [$table_mapping->getDedicatedDataTableName($current)];
    if ($current->isRevisionable()) {
      $tables[] = $table_mapping->getDedicatedRevisionTableName($current);
    }

    $schema = $this->container->get('database')->schema();
    $key_value = $this->container->get('keyvalue')->get('entity.storage_schema.sql');
    $schema_key = "$entity_type_id.field_schema_data.$field_name";
    $schema_data = $key_value->get($schema_key);
    foreach ($tables as $table) {
      $schema->dropIndex($table, $column);
      $schema->dropField($table, $column);
      unset($schema_data[$table]['fields'][$column], $schema_data[$table]['indexes'][$column]);
    }
    $key_value->set($schema_key, $schema_data);
    $last_installed->setLastInstalledFieldStorageDefinition($old);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Asserts whether a field storage has a pending definition update.
   */
  protected function assertStoragePending(bool $pending, string $entity_type_id, string $field_name): void {
    $change_list = $this->container->get('entity.definition_update_manager')->getChangeList();
    $changes = $change_list[$entity_type_id]['field_storage_definitions'] ?? [];
    $this->assertSame($pending, isset($changes[$field_name]), "$entity_type_id.$field_name pending update");
  }

  /**
   * Runs the role update hook.
   */
  protected function runRoleUpdate(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40206();
  }

  /**
   * Resets the entity cache and reloads an entity.
   */
  protected function reloadEntity(string $entity_type_id, int|string $id) {
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type_id);
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

}

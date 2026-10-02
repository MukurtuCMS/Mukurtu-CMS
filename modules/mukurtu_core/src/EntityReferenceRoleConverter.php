<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Core\Config\FileStorage;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;

/**
 * Converts existing entity_reference fields to reference-with-role.
 *
 * Used by update hooks. The new field type is the old storage plus one
 * nullable role column, but core refuses any field storage schema change once
 * a field has data, even a purely additive one. So the column is added by
 * hand, then the new definition and schema are recorded as installed.
 * Existing values keep their target_id and get no role.
 */
final class EntityReferenceRoleConverter {

  /**
   * The role-aware widget.
   */
  const WIDGET = 'mukurtu_entity_reference_role_autocomplete';

  /**
   * The role-aware formatter.
   */
  const FORMATTER = 'mukurtu_entity_reference_role_label';

  /**
   * Installs the shipped Role vocabulary and its language settings.
   */
  public static function installRoleVocabulary(): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $module_list = \Drupal::service('extension.list.module');

    $vocabulary_storage = $entity_type_manager->getStorage('taxonomy_vocabulary');
    if (!$vocabulary_storage->load('role')) {
      $config = new FileStorage($module_list->getPath('mukurtu_core') . '/config/install');
      $vocabulary_storage->createFromStorageRecord($config->read('taxonomy.vocabulary.role'))->save();
    }

    if (\Drupal::moduleHandler()->moduleExists('mukurtu_multilingual')) {
      $settings_storage = $entity_type_manager->getStorage('language_content_settings');
      if (!$settings_storage->load('taxonomy_term.role')) {
        $config = new FileStorage($module_list->getPath('mukurtu_multilingual') . '/config/install');
        $settings_storage->createFromStorageRecord($config->read('language.content_settings.taxonomy_term.role'))->save();
      }
    }
  }

  /**
   * Converts one field's installed storage to reference-with-role.
   *
   * @return bool
   *   TRUE if the field was converted, FALSE if there was nothing to do.
   */
  public static function convertStorage(string $entity_type_id, string $field_name): bool {
    $entity_type_manager = \Drupal::entityTypeManager();
    if (!$entity_type_manager->hasDefinition($entity_type_id)) {
      return FALSE;
    }
    $last_installed = \Drupal::service('entity.last_installed_schema.repository');
    $installed = $last_installed->getLastInstalledFieldStorageDefinitions($entity_type_id)[$field_name] ?? NULL;
    $definition = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id)[$field_name] ?? NULL;
    if (!$installed || !$definition
      || $installed->getType() !== 'entity_reference'
      || $definition->getType() !== 'mukurtu_entity_reference_role') {
      return FALSE;
    }

    $table_mapping = $entity_type_manager->getStorage($entity_type_id)->getTableMapping();
    // Dedicated tables use the field-prefixed column name as the index name
    // too; see SqlContentEntityStorageSchema::getFieldIndexName().
    $column = $table_mapping->getFieldColumnName($definition, 'role_target_id');
    $spec = EntityReferenceRoleItem::schema($definition)['columns']['role_target_id'] + ['not null' => FALSE];

    $schema = \Drupal::database()->schema();
    $key_value = \Drupal::keyValue('entity.storage_schema.sql');
    $schema_key = "$entity_type_id.field_schema_data.$field_name";
    $schema_data = $key_value->get($schema_key, []);

    $tables = [$table_mapping->getDedicatedDataTableName($definition)];
    if ($definition->isRevisionable()) {
      $tables[] = $table_mapping->getDedicatedRevisionTableName($definition);
    }
    foreach ($tables as $table) {
      if (!$schema->fieldExists($table, $column)) {
        $schema->addField($table, $column, $spec, [
          'fields' => [$column => $spec],
          'indexes' => [$column => [$column]],
        ]);
      }
      $schema_data[$table]['fields'][$column] = $spec;
      $schema_data[$table]['indexes'][$column] = [$column];
    }

    $key_value->set($schema_key, $schema_data);
    $last_installed->setLastInstalledFieldStorageDefinition($definition);
    // Storage handlers cache their table mapping from the installed
    // definitions, so drop them; otherwise anything loading this entity type
    // later in the same request (another update hook) reads without the
    // role column.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $entity_type_manager->clearCachedDefinitions();
    return TRUE;
  }

  /**
   * Moves a field's form and view displays onto the role-aware plugins.
   *
   * Every form display moves to the role widget, since no other widget is
   * offered for the new type and one that ignored roles would wipe them on
   * save. View displays only change where they use the plain label
   * formatter; any other entity reference formatter keeps working as is.
   */
  public static function updateDisplays(string $entity_type_id, string $field_name): void {
    $config_factory = \Drupal::configFactory();
    $key = "content.$field_name";

    foreach ($config_factory->listAll("core.entity_form_display.$entity_type_id.") as $name) {
      $display = $config_factory->getEditable($name);
      $component = $display->get($key);
      if (!$component || $component['type'] === self::WIDGET) {
        continue;
      }
      $settings = $component['settings'] ?? [];
      $component['type'] = self::WIDGET;
      $component['settings'] = [
        'match_operator' => $settings['match_operator'] ?? 'CONTAINS',
        'match_limit' => !empty($settings['match_limit']) ? $settings['match_limit'] : 10,
        'size' => $settings['size'] ?? 60,
        'placeholder' => $settings['placeholder'] ?? '',
      ];
      $display->set($key, $component)->save();
    }

    foreach ($config_factory->listAll("core.entity_view_display.$entity_type_id.") as $name) {
      $display = $config_factory->getEditable($name);
      if ($display->get("$key.type") === 'entity_reference_label') {
        $display->set("$key.type", self::FORMATTER)->save();
      }
    }
  }

}

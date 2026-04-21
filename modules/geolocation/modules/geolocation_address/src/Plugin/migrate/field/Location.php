<?php

namespace Drupal\geolocation_address\Plugin\migrate\field;

use Drupal\address\Plugin\migrate\field\AddressField as LocationAddress;
use Drupal\Core\Plugin\PluginBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * A migrate field plugin for Drupal 7 Location CCK field values.
 *
 * The plugin extends Address module's plugin definition to migrate address-like
 * field values into an address field, and geolocation values to a separate
 * geolocation field.
 *
 * @see geolocation_address_migrate_field_info_alter()
 * @see geolocation_migrate_field_info_alter()
 */
class Location extends LocationAddress {

  /**
   * Suffix added to the geolocation field.
   *
   * @var string
   */
  const GEOLOCATION_FIELD_NAME_SUFFIX = '_geoloc';

  /**
   * Get geolocation field name.
   *
   * @param string $field_name
   *   The location field's name in the source.
   *
   * @return string
   *   The field name of the geolocation field.
   */
  public static function getGeolocationFieldName(string $field_name) {
    return mb_substr($field_name, 0, FieldStorageConfig::NAME_MAX_LENGTH - mb_strlen(static::GEOLOCATION_FIELD_NAME_SUFFIX)) . static::GEOLOCATION_FIELD_NAME_SUFFIX;
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    parent::defineValueProcessPipeline($migration, $field_name, $data);
    // Address cannot store geographical locations, so we need a separate
    // geolocation field.
    $geolocation_field_name = self::getGeolocationFieldName($field_name);
    $migration->mergeProcessOfProperty($geolocation_field_name, [
      'plugin' => 'location_to_geolocation',
      'source' => $field_name,
    ]);

    // Add the new geolocation field's migration as a required dependency.
    $migration_dependencies = $migration->getMigrationDependencies() + ['required' => []];
    $geolocation_field_instance_migration_plugin_id = implode(PluginBase::DERIVATIVE_SEPARATOR, [
      'd7_field_instance_location',
      $data['entity_type'],
      $data['bundle'],
    ]);
    $geolocation_field_widget_migration_plugin_id = implode(PluginBase::DERIVATIVE_SEPARATOR, [
      'd7_field_instance_widget_settings_location',
      $data['entity_type'],
      $data['bundle'],
    ]);
    $migration_dependencies['required'] = array_unique(
      array_merge(
        array_values($migration_dependencies['required']),
        [
          $geolocation_field_instance_migration_plugin_id,
          $geolocation_field_widget_migration_plugin_id,
        ]
      )
    );
    $migration->set('migration_dependencies', $migration_dependencies);
  }

}

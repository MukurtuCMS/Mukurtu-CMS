<?php

namespace Drupal\geolocation\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * A migrate field plugin for Drupal 7 Location CCK field values.
 *
 * The plugin replaces even Address module's plugin definition.
 *
 * @see geolocation_migrate_field_info_alter()
 */
class Location extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterFieldInstanceMigration(MigrationInterface $migration) {
    parent::alterFieldInstanceMigration($migration);
    $additional_processes = [
      [
        'plugin' => 'default_value',
        'default_value' => [],
      ],
    ];
    $migration->mergeProcessOfProperty('settings', $additional_processes);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'location_default' => 'geolocation_latlng',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'location' => 'geolocation_latlng',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $migration->mergeProcessOfProperty($field_name, [
      'plugin' => 'location_to_geolocation',
      'source' => $field_name,
    ]);
  }

}

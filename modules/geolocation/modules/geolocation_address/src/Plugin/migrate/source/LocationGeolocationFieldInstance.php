<?php

namespace Drupal\geolocation_address\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\field\Plugin\migrate\source\d7\FieldInstance;
use Drupal\geolocation_address\Plugin\migrate\field\Location;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Drupal 7 geolocation field storage source for D7 location fields.
 *
 * @MigrateSource(
 *   id = "d7_field_instance_location",
 *   core = {7},
 *   source_module = "location_cck",
 *   destination_module = "geolocation_address"
 * )
 */
class LocationGeolocationFieldInstance extends FieldInstance {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    $configuration += [
      'entity_type' => NULL,
      'bundle' => NULL,
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->condition('fc.type', 'location');
    [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ] = $this->configuration;

    if ($entity_type) {
      $query->condition('fci.entity_type', $entity_type);

      if ($bundle) {
        $query->condition('fci.bundle', $bundle);
      }
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row, $keep = TRUE) {
    if (!parent::prepareRow($row)) {
      return FALSE;
    }

    $geolocation_field_name = Location::getGeolocationFieldName($row->getSourceProperty('field_name'));
    $row->setSourceProperty('geolocation_field_name', $geolocation_field_name);

    return TRUE;
  }

}

<?php

namespace Drupal\geolocation\Plugin\migrate\process;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process plugin that converts D7 location field values to D8|D9 geolocation.
 *
 * @MigrateProcessPlugin(
 *   id = "location_to_geolocation"
 * )
 */
class LocationToGeolocation extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration entity.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * Constructs a new selection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value) || empty($value['lid'])) {
      // Empty field value.
      return NULL;
    }

    $source_plugin = $this->migration->getSourcePlugin();
    assert($source_plugin instanceof DrupalSqlBase);
    $source_database = $source_plugin->getDatabase();

    try {
      $location_result = $source_database->select('location', 'l')
        ->fields('l')
        ->condition('l.lid', $value['lid'])
        ->execute()
        ->fetchAllAssoc('lid', \PDO::FETCH_ASSOC);

      if (count($location_result) === 1) {
        $location_raw = reset($location_result);
        [
          'latitude' => $lat,
          'longitude' => $lng,
        ] = $location_raw;

        // The "0.000000" values are the default values in Drupal 7, but that's
        // also a valid coordinate. Anyway, let's assume that it means the field
        // is empty.
        return (((string) $lat) !== '0.000000' && ((string) $lng) !== '0.000000')
          ? ['lat' => $lat, 'lng' => $lng]
          : NULL;
      }
    }
    catch (DatabaseExceptionWrapper $e) {
    }

    return NULL;
  }

}

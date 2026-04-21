<?php

namespace Drupal\geofield\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a geofield field mapper.
 *
 * @FeedsTarget(
 *   id = "geofield_feeds_target",
 *   field_types = {"geofield"}
 * )
 */
class Geofield extends FieldTargetBase implements ContainerFactoryPluginInterface {

  /**
   * The Settings object or array.
   *
   * @var mixed
   */
  protected $settings;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a Geofield FeedsTarget object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MessengerInterface $messenger) {
    $target_definition = $configuration['target_definition'];
    $this->settings = $target_definition->getFieldDefinition()->getSettings();
    $this->messenger = $messenger;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('lat')
      ->addProperty('lon')
      ->addProperty('value');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValues(array $values) {
    $results = [];
    $coordinates = [];

    foreach ($values as $delta => $columns) {
      try {
        $this->prepareValue($delta, $columns);
        foreach ($columns as $column => $value) {

          // Add Lat/Lon Coordinates.
          if (in_array($column, ['lat', 'lon']) && isset($value)) {
            foreach ($value as $item) {
              $coordinates[$column][] = $item;
            }
          }

          // Raw Geometry value (i.e. WKT or GeoJson).
          if ($column == 'value') {
            $results[]['value'] = $value;
          }
        }
      }
      catch (EmptyFeedException $e) {
        $this->messenger->addError($e->getMessage());
        return FALSE;
      }
    }

    // Transform Lat/Lon Coordinates couples into WKT Points.
    if (!empty($coordinates)) {
      $count_of_coordinates = count($coordinates['lat']);
      for ($i = 0; $i < $count_of_coordinates; $i++) {
        // If either Latitude or Longitude is not null/zero then set a POINT.
        if (!empty($coordinates['lat'][$i]) || !empty($coordinates['lon'][$i])) {
          $results[]['value'] = "POINT (" . $coordinates['lon'][$i] . " " . $coordinates['lat'][$i] . ")";
        }
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {

    // Here is been preparing values for Lat/Lon coordinates.
    foreach ($values as $column => $value) {
      if (in_array($column, ['lat', 'lon']) && isset($value)) {
        $separated_coordinates = explode(" ", $value);
        $values[$column] = [];

        foreach ($separated_coordinates as $coordinate) {
          $values[$column][] = (float) $coordinate;
        }
      }
    }

    // Latitude and Longitude should be a pair, if not throw EmptyFeedException.
    if (count($values['lat'] ?? []) != count($values['lon'] ?? [])) {
      throw new EmptyFeedException('Latitude and Longitude should be a pair. Change your file and import again.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {

    $summary = parent::getSummary();

    $summary[] = [
      '#markup' => $this->t('<b>Instructions: </b>Use ONLY Centroid
Latitude & Centroid Longitude in case of Lat/Lon coupled values<br>OR ONLY Geometry in case of WKT or GeoJson format. Don\'t use both.'),
    ];

    return $summary;
  }

}

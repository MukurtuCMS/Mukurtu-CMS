<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Class Geolocation Geometry Base.
 *
 * @package Drupal\geolocation_geometry\Plugin\Field\FieldType
 */
abstract class GeolocationGeometryBase extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'geometry' => [
          'description' => 'Stores the geometry',
          'type' => 'text',
          'mysql_type' => 'geometry',
          'pgsql_type' => 'geometry',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'wkt' => [
          'description' => 'Stores the geometry as Well Known Text',
          'type' => 'text',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'geojson' => [
          'description' => 'Stores the geometry as GeoJSON',
          'type' => 'text',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'data' => [
          'description' => 'Serialized array of additional data.',
          'type' => 'blob',
          'size' => 'big',
          'not null' => FALSE,
          'serialize' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $geom_type = explode("_", $field_definition->getType())[2];

    $properties['geometry'] = DataDefinition::create('string')
      ->setComputed('true')
      ->setLabel(t('Geometry'));
    $properties['wkt'] = DataDefinition::create('string')->setLabel(t('WKT'))
      ->addConstraint(
        'GeometryType',
        ['geometryType' => $geom_type, 'type' => 'WKT']
      );
    $properties['geojson'] = DataDefinition::create('string')->setLabel(t('GeoJSON'))
      ->addConstraint(
        'GeometryType',
        ['geometryType' => $geom_type, 'type' => 'GeoJSON']
      );
    $properties['data'] = MapDataDefinition::create()->setLabel(t('Meta data'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    parent::postSave($update);

    $entity = $this->getEntity();
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());

    if (!is_a($entity_storage, '\Drupal\Core\Entity\Sql\SqlContentEntityStorage')) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $entity_storage */
    $table_mapping = $entity_storage->getTableMapping();
    $field_storage_definition = $this->getFieldDefinition()->getFieldStorageDefinition();

    if ($entity->getEntityType()->isRevisionable()) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $query = \Drupal::database()->update($table_mapping->getDedicatedRevisionTableName($field_storage_definition));
      if (!empty($this->values['geojson'])) {
        $query->expression($field_storage_definition->getName() . '_geometry', 'ST_GeomFromGeoJSON(' . $field_storage_definition->getName() . '_geojson)');
        $query->expression($field_storage_definition->getName() . '_wkt', 'ST_AsText(ST_GeomFromGeoJSON(' . $field_storage_definition->getName() . '_geojson))');
      }
      elseif (!empty($this->values['wkt'])) {
        $query->expression($field_storage_definition->getName() . '_geometry', 'ST_GeomFromText(' . $field_storage_definition->getName() . '_wkt, 4326)');
        $query->expression($field_storage_definition->getName() . '_geojson', 'ST_AsGeoJSON(ST_GeomFromText(' . $field_storage_definition->getName() . '_wkt, 4326))');
      }

      if (empty($this->values['data'])) {
        $query->fields([$field_storage_definition->getName() . '_data' => serialize(NULL)]);
      }
      $query->condition('entity_id', $entity->id());
      $query->condition('revision_id', $entity->getRevisionId());
      $query->condition('bundle', $entity->bundle());
      $query->condition('delta', $this->getName());
      $query->condition('langcode', $this->getLangcode());
      $query->execute();
    }

    $query = \Drupal::database()->update($table_mapping->getDedicatedDataTableName($field_storage_definition));
    if (!empty($this->values['geojson'])) {
      $query->expression($field_storage_definition->getName() . '_geometry', 'ST_GeomFromGeoJSON(' . $field_storage_definition->getName() . '_geojson)');
      $query->expression($field_storage_definition->getName() . '_wkt', 'ST_AsText(ST_GeomFromGeoJSON(' . $field_storage_definition->getName() . '_geojson))');
    }
    elseif (!empty($this->values['wkt'])) {
      $query->expression($field_storage_definition->getName() . '_geometry', 'ST_GeomFromText(' . $field_storage_definition->getName() . '_wkt, 4326)');
      $query->expression($field_storage_definition->getName() . '_geojson', 'ST_AsGeoJSON(ST_GeomFromText(' . $field_storage_definition->getName() . '_wkt, 4326))');
    }

    if (empty($this->values['data'])) {
      $query->fields([$field_storage_definition->getName() . '_data' => serialize(NULL)]);
    }
    $query->condition('entity_id', $entity->id());
    $query->condition('bundle', $entity->bundle());
    $query->condition('delta', $this->getName());
    $query->condition('langcode', $this->getLangcode());
    $query->execute();

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $coordinates = self::getRandomCoordinates();
    $values['geojson'] = '{"type": "Point", "coordinates": [' . $coordinates['longitude'] . ', ' . $coordinates['latitude'] . ']}';
    return $values;
  }

  /**
   * Return a random set of latitude/longitude.
   *
   * @return float[]
   *   Coordinates.
   */

  /**
   * Return a random set of latitude/longitude.
   *
   * @return float[]
   *   Coordinates.
   */
  protected static function getRandomCoordinates(array $reference_point = NULL, float $range = 5) {
    if ($reference_point) {
      return [
        'latitude' => rand(
          (int) max(-89, ($reference_point['latitude'] - $range)),
          (int) min(90, ($reference_point['latitude'] + $range))
        ) - (rand(0, 999999) / 1000000),
        'longitude' => rand(
          (int) max(-179, ($reference_point['longitude'] - $range)),
          (int) min(180, ($reference_point['longitude'] + $range))
        ) - (rand(0, 999999) / 1000000),
      ];
    }

    return [
      'latitude' => rand(-89, 90) - rand(0, 999999) / 1000000,
      'longitude' => rand(-179, 180) - rand(0, 999999) / 1000000,
    ];
  }

  /**
   * Sort by angle from center point.
   *
   * @param array $coordinate1
   *   Coordinate.
   * @param array $coordinate2
   *   Coordinate.
   * @param array $center_point
   *   Center Coordinate.
   *
   * @return bool
   *   Sort.
   */
  protected static function sortCoordinatesByAngle(array $coordinate1, array $coordinate2, array $center_point = []) {
    if (empty($center_point)) {
      $center_point = ['latitude' => 0, 'longitude' => 0];
    }
    return atan2($coordinate1['latitude'] - $center_point['latitude'], $coordinate1['longitude'] - $center_point['longitude']) * 180 / M_PI >
      atan2($coordinate2['latitude'] - $center_point['latitude'], $coordinate2['longitude'] - $center_point['longitude']) * 180 / M_PI;
  }

  /**
   * Get a center latitude,longitude from an array of like geopoints.
   *
   * For Example:
   * $data = array
   * (
   *   0 = > array(45.849382, 76.322333),
   *   1 = > array(45.843543, 75.324143),
   *   2 = > array(45.765744, 76.543223),
   *   3 = > array(45.784234, 74.542335)
   * );
   *
   * @param array $coordinates
   *   Coordinates.
   */
  protected static function getCenterFromCoordinates(array $coordinates) {
    $x = 0.0;
    $y = 0.0;
    $z = 0.0;

    foreach ($coordinates as $coordinate) {
      $lat = $coordinate['latitude'] * pi() / 180;
      $lon = $coordinate['longitude'] * pi() / 180;

      $a = cos($lat) * cos($lon);
      $b = cos($lat) * sin($lon);
      $c = sin($lat);

      $x += $a;
      $y += $b;
      $z += $c;
    }

    $num_coords = count($coordinates);

    $x /= $num_coords;
    $y /= $num_coords;
    $z /= $num_coords;

    $lon = atan2($y, $x);
    $hyp = sqrt($x * $x + $y * $y);
    $lat = atan2($z, $hyp);

    return ['latitude' => $lat * 180 / pi(), 'longitude' => $lon * 180 / pi()];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return (empty($this->get('wkt')->getValue()) && empty($this->get('geojson')->getValue()));
  }

}

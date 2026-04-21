<?php

namespace Drupal\geolocation\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\geolocation\TypedData\GeolocationComputed;

/**
 * Plugin implementation of the 'geolocation' field type.
 *
 * @FieldType(
 *   id = "geolocation",
 *   label = @Translation("Geolocation"),
 *   description = @Translation("This field stores location data (lat, lng)."),
 *   default_widget = "geolocation_latlng",
 *   default_formatter = "geolocation_latlng"
 * )
 */
class GeolocationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'lat' => [
          'description' => 'Stores the latitude value',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'lng' => [
          'description' => 'Stores the longitude value',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'lat_sin' => [
          'description' => 'Stores the sine of latitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'lat_cos' => [
          'description' => 'Stores the cosine of latitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'lng_rad' => [
          'description' => 'Stores the radian longitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'data' => [
          'description' => 'Serialized array of geolocation meta information.',
          'type' => 'blob',
          'size' => 'big',
          'not null' => FALSE,
          'serialize' => TRUE,
        ],
      ],
      'indexes' => [
        'lat' => ['lat'],
        'lng' => ['lng'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['lat'] = DataDefinition::create('float')
      ->setLabel(t('Latitude'));

    $properties['lng'] = DataDefinition::create('float')
      ->setLabel(t('Longitude'));

    $properties['lat_sin'] = DataDefinition::create('float')
      ->setLabel(t('Latitude sine'))
      ->setComputed(TRUE);

    $properties['lat_cos'] = DataDefinition::create('float')
      ->setLabel(t('Latitude cosine'))
      ->setComputed(TRUE);

    $properties['lng_rad'] = DataDefinition::create('float')
      ->setLabel(t('Longitude radian'))
      ->setComputed(TRUE);

    $properties['data'] = MapDataDefinition::create()
      ->setLabel(t('Meta data'));

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Computed lat,lng value'))
      ->setComputed(TRUE)
      ->setInternal(FALSE)
      ->setClass(GeolocationComputed::class);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['lat'] = (float) rand(-89, 90) - rand(0, 999999) / 1000000;
    $values['lng'] = (float) rand(-179, 180) - rand(0, 999999) / 1000000;
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $lat = $this->get('lat')->getValue();
    $lng = $this->get('lng')->getValue();
    return $lat === NULL || $lat === '' || $lng === NULL || $lng === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Update the values and return them.
    foreach ($this->properties as $name => $property) {
      $value = $property->getValue();
      // Only write NULL values if the whole map is not NULL.
      if (isset($this->values) || isset($value)) {
        $this->values[$name] = $value;
      }
    }

    // See #3024504 for an explanation.
    if (array_key_exists('data', $this->values) && empty($this->values['data'])) {
      unset($this->values['data']);
    }

    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    parent::setValue($values, $notify);

    // If the values being set do not contain lat_sin, lat_cos or lng_rad,
    // recalculate them.
    if (
      (
        empty($values['lat_sin'])
        || empty($values['lat_cos'])
        || empty($values['lat_rad'])
      )
      && !$this->isEmpty()
    ) {
      $this->get('lat_sin')->setValue(sin(deg2rad(trim($this->get('lat')->getValue()))), FALSE);
      $this->get('lat_cos')->setValue(cos(deg2rad(trim($this->get('lat')->getValue()))), FALSE);
      $this->get('lng_rad')->setValue(deg2rad(trim($this->get('lng')->getValue())), FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    parent::onChange($property_name, $notify);

    // Update the calculated properties if lat or lng changed.
    if (
      (
        $property_name == 'lat'
        || $property_name == 'lng'
      )
      && !$this->isEmpty()
    ) {
      $this->get('lat_sin')->setValue(sin(deg2rad(trim($this->get('lat')->getValue()))), FALSE);
      $this->get('lat_cos')->setValue(cos(deg2rad(trim($this->get('lat')->getValue()))), FALSE);
      $this->get('lng_rad')->setValue(deg2rad(trim($this->get('lng')->getValue())), FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $this->get('lat')->setValue(trim($this->get('lat')->getValue()));
    $this->get('lng')->setValue(trim($this->get('lng')->getValue()));
  }

  /**
   * Transform sexagesimal notation to float.
   *
   * Sexagesimal means a string like - X° Y' Z"
   *
   * @param string $sexagesimal
   *   String in DMS notation.
   *
   * @return float|false
   *   The regular float notation or FALSE if not sexagesimal.
   */
  public static function sexagesimalToDecimal($sexagesimal = '') {
    $pattern = "/(?<degree>-?\d{1,3})°[ ]?((?<minutes>\d{1,2})')?[ ]?((?<seconds>(\d{1,2}|\d{1,2}\.\d+))\")?/";
    preg_match($pattern, $sexagesimal, $gps_matches);
    if (
    !empty($gps_matches)
    ) {
      $value = $gps_matches['degree'];
      if (!empty($gps_matches['minutes'])) {
        $value += $gps_matches['minutes'] / 60;
      }
      if (!empty($gps_matches['seconds'])) {
        $value += $gps_matches['seconds'] / 3600;
      }
    }
    else {
      return FALSE;
    }
    return $value;
  }

  /**
   * Transform decimal notation to sexagesimal.
   *
   * Sexagesimal means a string like - X° Y' Z"
   *
   * @param float|string $decimal
   *   Either float or float-castable location.
   *
   * @return string
   *   The sexagesimal notation or FALSE on error.
   */
  public static function decimalToSexagesimal($decimal = '') {
    $negative = FALSE;
    $decimal = (float) $decimal;

    if ($decimal < 0) {
      $negative = TRUE;
      $decimal = abs($decimal);
    }

    $degrees = floor($decimal);
    $rest = $decimal - $degrees;
    $minutes = floor($rest * 60);
    $rest = $rest * 60 - $minutes;
    $seconds = round($rest * 60, 4);

    $value = $degrees . '°';
    if (!empty($minutes)) {
      $value .= ' ' . $minutes . '\'';
    }
    if (!empty($seconds)) {
      $value .= ' ' . $seconds . '"';
    }

    if ($negative) {
      $value = '-' . $value;
    }

    return $value;
  }

}

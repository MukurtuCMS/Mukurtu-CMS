<?php

namespace Drupal\geolocation_geometry\Plugin\Tamper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for filtering data.
 *
 * @Tamper(
 *   id = "long_lat_to_point",
 *   label = @Translation("Long/Lat to Point"),
 *   description = @Translation("Translate longitude and latitude to a WKT point."),
 *   category = "Geolocation",
 *   handle_multiples = TRUE
 * )
 */
class LongLatToPoint extends TamperBase {

  /**
   * Name for the 'longitude' settings field.
   *
   * @var string
   */
  const SETTING_LONGITUDE_FIELD = 'longitude';

  /**
   * Name for the 'latitude' settings field.
   *
   * @var string
   */
  const SETTING_LATITUDE_FIELD = 'latitude';

  /**
   * Name for the 'format' settings field.
   *
   * @var string
   */
  const SETTING_FORMAT_FIELD = 'format';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config[self::SETTING_FORMAT_FIELD] = 'wkt';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {
    $source = $item->getSource();

    $longitude = $source[$this->getSetting(self::SETTING_LONGITUDE_FIELD)];
    $latitude = $source[$this->getSetting(self::SETTING_LATITUDE_FIELD)];

    if (!(is_numeric($longitude) && is_numeric($latitude))) {
      throw new TamperException('Longitude and latitude must be provided as numeric values.');
    }
    elseif ($latitude < -90 || $latitude > 90) {
      throw new TamperException('Latitude must be a numeric value between -90 and 90.');
    }
    elseif ($longitude < -180 || $longitude > 180) {
      throw new TamperException('Longitude must be a numeric value between -180 and 180.');
    }

    switch ($this->getSetting(self::SETTING_FORMAT_FIELD)) {
      case 'wkt':
        return $this->formatWkt($longitude, $latitude);

      case 'geojson':
        return $this->formatGeoJson($longitude, $latitude);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form[self::SETTING_LONGITUDE_FIELD] = [
      '#type' => 'select',
      '#title' => $this->t('Longitude field'),
      '#options' => $this->getSourceOptions(),
      '#required' => TRUE,
      '#default_value' => $this->getSetting(self::SETTING_LONGITUDE_FIELD),
    ];

    $form[self::SETTING_LATITUDE_FIELD] = [
      '#type' => 'select',
      '#title' => $this->t('Latitude field'),
      '#options' => $this->getSourceOptions(),
      '#required' => TRUE,
      '#default_value' => $this->getSetting(self::SETTING_LATITUDE_FIELD),
    ];

    $form[self::SETTING_FORMAT_FIELD] = [
      '#type' => 'radios',
      '#title' => $this->t('Format'),
      '#options' => [
        'wkt' => $this->t('WKT (Well Known Text)'),
        'geojson' => $this->t('GeoJSON'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->getSetting(self::SETTING_FORMAT_FIELD),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

  /**
   * Get the source-fields available to select as either longitude or latitude.
   *
   * @return array
   *   Array of possible source fields, based on the feed source options.
   */
  protected function getSourceOptions() {
    return array_filter($this->sourceDefinition->getList());
  }

  /**
   * Return coordinates as a GeoJSON definition.
   *
   * @param float $long
   *   Longitude.
   * @param float $lat
   *   Latitude.
   *
   * @return string
   *   The coordinates as a GeoJSON definition.
   */
  protected function formatGeoJson($long, $lat) {
    return json_encode([
      'type' => 'Point',
      'coordinates' => [
        $long,
        $lat,
      ],
    ]);
  }

  /**
   * Return coordinates as a WKT definition.
   *
   * @param float $long
   *   Longitude.
   * @param float $lat
   *   Latitude.
   *
   * @return string
   *   The coordinates as a WKT definition.
   */
  protected function formatWkt($long, $lat) {
    return sprintf('POINT (%f %f)', $long, $lat);
  }

}

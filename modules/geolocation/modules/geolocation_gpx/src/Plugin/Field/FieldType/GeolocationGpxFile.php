<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldType;

use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation of the 'image' field type.
 *
 * @FieldType(
 *   id = "geolocation_gpx_file",
 *   label = @Translation("Geolocation GPX File"),
 *   description = @Translation("This field stores the ID of an geolocation gpx file as an integer value."),
 *   category = "Geolocation",
 *   default_widget = "geolocation_gpx_file",
 *   default_formatter = "geolocation_gpx_file",
 * )
 */
class GeolocationGpxFile extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $default_settings = parent::defaultFieldSettings();
    $default_settings['file_extensions'] = 'gpx xml';

    return $default_settings;
  }

}

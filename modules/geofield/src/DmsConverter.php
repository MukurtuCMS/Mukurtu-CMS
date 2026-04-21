<?php

namespace Drupal\geofield;

/**
 * Helper class to convert point object from one format to the other.
 */
class DmsConverter implements DmsConverterInterface {

  /**
   * {@inheritdoc}
   */
  public static function dmsToDecimal(DmsPoint $point) {
    $lon_data = $point->getLon();
    $lat_data = $point->getLat();
    $lon = round($lon_data['degrees'] + ($lon_data['minutes'] / 60) + ($lon_data['seconds'] / 3600), 10);
    $lat = round($lat_data['degrees'] + ($lat_data['minutes'] / 60) + ($lat_data['seconds'] / 3600), 10);

    $lon = ($lon_data['orientation'] == 'W') ? (-1 * $lon) : $lon;
    $lat = ($lat_data['orientation'] == 'S') ? (-1 * $lat) : $lat;

    return [$lon, $lat];
  }

  /**
   * {@inheritdoc}
   */
  public static function decimalToDms($lon, $lat) {
    $lat_direction = $lat < 0 ? 'S' : 'N';
    $lon_direction = $lon < 0 ? 'W' : 'E';

    $lat_in_degrees = floor(abs($lat));
    $lon_in_degrees = floor(abs($lon));

    $la_decimal = (abs($lat) - $lat_in_degrees) * 60;
    $lon_decimal = (abs($lon) - $lon_in_degrees) * 60;

    $lat_minutes = floor($la_decimal);
    $lon_minutes = floor($lon_decimal);

    $la_decimal = ($la_decimal - $lat_minutes) * 60;
    $lon_decimal = ($lon_decimal - $lon_minutes) * 60;

    $lat_seconds = round($la_decimal);
    $lon_seconds = round($lon_decimal);

    return new DmsPoint([
      'orientation' => $lon_direction,
      'degrees' => $lon_in_degrees,
      'minutes' => $lon_minutes,
      'seconds' => $lon_seconds,
    ],
      [
        'orientation' => $lat_direction,
        'degrees' => $lat_in_degrees,
        'minutes' => $lat_minutes,
        'seconds' => $lat_seconds,
      ]
    );
  }

}

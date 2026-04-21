<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\ViewsContextTrait;

/**
 * Derive center from proximity argument.
 *
 * @Location(
 *   id = "views_boundary_argument",
 *   name = @Translation("Boundary argument - center only"),
 *   description = @Translation("Set map center from boundary argument."),
 * )
 */
class ViewsBoundaryArgument extends LocationBase implements LocationInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions($context): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
      foreach ($displayHandler->getHandlers('argument') as $delta => $argument) {
        if ($argument->getPluginId() === 'geolocation_argument_boundary') {
          $options[$delta] = $argument->adminLabel();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($location_option_id, array $location_option_settings, $context = NULL) {
    if ($displayHandler = self::getViewsDisplayHandler($context)) {

      /** @var \Drupal\geolocation\Plugin\views\argument\BoundaryArgument $argument */
      $argument = $displayHandler->getHandler('argument', $location_option_id);
      if (empty($argument)) {
        return FALSE;
      }
      $values = $argument->getParsedBoundary();

      // See documentation at
      // http://tubalmartin.github.io/spherical-geometry-php/#LatLngBounds
      $latitude = ($values['lat_south_west'] + $values['lat_north_east']) / 2;
      $longitude = ($values['lng_south_west'] + $values['lng_north_east']) / 2;
      if ($values['lng_south_west'] > $values['lng_north_east']) {
        $longitude = $longitude == 0 ? 180 : fmod((fmod((($longitude + 180) - -180), 360) + 360), 360) + -180;
      }

      return [
        'lat' => $latitude,
        'lng' => $longitude,
      ];
    }

    return parent::getCoordinates($location_option_id, $location_option_settings, $context);
  }

}

<?php

namespace Drupal\geolocation_geometry\Plugin\views\filter;

use Drupal\geolocation\Plugin\views\filter\ProximityFilter;
use Drupal\geolocation_geometry\GeometryProximityTrait;

/**
 * Filter handler for search keywords.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geolocation_geometry_filter_proximity")
 */
class GeoProximityFilter extends ProximityFilter {

  use GeometryProximityTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = $this->ensureMyTable();
    $this->value['value'] = self::convertDistance($this->value['value'], $this->options['unit']);

    if (
      array_key_exists('lat', $this->value)
      && array_key_exists('lng', $this->value)
    ) {
      $center = [
        'lat' => (float) $this->value['lat'],
        'lng' => (float) $this->value['lng'],
      ];
    }
    else {
      $center = $this->locationInputManager->getCoordinates((array) $this->value['center'], $this->options['location_input'], $this);
    }

    if (
      empty($center)
      || !is_numeric($center['lat'])
      || !is_numeric($center['lng'])
      || empty($this->value['value'])
    ) {
      return;
    }

    // Build the query expression.
    $expression = self::getGeometryProximityQueryFragment($table, $this->realField, $center['lat'], $center['lng']);

    // Get operator info.
    $info = $this->operators();

    // Make sure a callback exists and add a where expression for the chosen
    // operator.
    if (!empty($info[$this->operator]['method']) && method_exists($this, $info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($expression);
    }
  }

}

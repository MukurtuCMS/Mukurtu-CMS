<?php

namespace Drupal\geolocation_geometry\Plugin\views\argument;

use Drupal\geolocation\Plugin\views\argument\ProximityArgument;
use Drupal\geolocation_geometry\GeometryProximityTrait;

/**
 * Argument handler for geolocation proximity.
 *
 * Argument format should be in the following format:
 * "37.7749295,-122.41941550000001<=5mi" (defaults to km).
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_geometry_argument_proximity")
 */
class GeoProximityArgument extends ProximityArgument {

  use GeometryProximityTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormula() {
    // Parse argument for reference location.
    $values = $this->getParsedReferenceLocation();
    // Make sure we have enough information to start with.
    if (
      !empty($values)
      && isset($values['lat'])
      && isset($values['lng'])
      && isset($values['distance'])
    ) {
      $distance = self::convertDistance((float) $values['distance'], $values['unit']);

      // Build a formula for the where clause.
      $formula = self::getGeometryProximityQueryFragment($this->tableAlias, $this->realField, $values['lat'], $values['lng']);
      // Set the operator and value for the query.
      $this->operator = $values['operator'];
      $this->distance = $distance;

      return !empty($formula) ? str_replace('***table***', $this->tableAlias, $formula) : FALSE;
    }
    else {
      return FALSE;
    }
  }

}

<?php

namespace Drupal\geolocation_geometry\Plugin\views\field;

use Drupal\geolocation\Plugin\views\field\ProximityField;
use Drupal\geolocation_geometry\GeometryProximityTrait;

/**
 * Field handler for geolocation field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geolocation_geometry_field_proximity")
 */
class GeoProximityField extends ProximityField {

  use GeometryProximityTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    $center = $this->getCenter();
    if (empty($center)) {
      return;
    }

    // Build the query expression.
    $expression = self::getGeometryProximityQueryFragment($this->ensureMyTable(), $this->realField, $center['lat'], $center['lng']);

    // Get a placeholder for this query and save the field_alias for it.
    // Remove the initial ':' from the placeholder and avoid collision with
    // original field name.
    $this->field_alias = $query->addField(NULL, $expression, substr($this->placeholder(), 1));
  }

}

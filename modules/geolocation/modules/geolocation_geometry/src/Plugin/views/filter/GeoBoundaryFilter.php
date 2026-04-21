<?php

namespace Drupal\geolocation_geometry\Plugin\views\filter;

use Drupal\geolocation\Plugin\views\filter\BoundaryFilter;
use Drupal\geolocation_geometry\GeometryBoundaryTrait;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Filter handler for search keywords.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geolocation_geometry_filter_boundary")
 */
class GeoBoundaryFilter extends BoundaryFilter {

  use GeometryBoundaryTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!($this->query instanceof Sql)) {
      return;
    }

    if (empty($this->value)) {
      return;
    }

    // Get the field alias.
    $lat_north_east = $this->value['lat_north_east'];
    $lng_north_east = $this->value['lng_north_east'];
    $lat_south_west = $this->value['lat_south_west'];
    $lng_south_west = $this->value['lng_south_west'];

    if (
      !is_numeric($lat_north_east)
      || !is_numeric($lng_north_east)
      || !is_numeric($lat_south_west)
      || !is_numeric($lng_south_west)
    ) {
      return;
    }

    $placeholder = $this->placeholder() . '_boundary_geojson';

    $this->query->addWhereExpression(
      $this->options['group'],
      self::getGeometryBoundaryQueryFragment($this->ensureMyTable(), $this->realField, $placeholder),
      self::getGeometryBoundaryQueryValue($placeholder, $lat_north_east, $lng_north_east, $lat_south_west, $lng_south_west)
    );
  }

}

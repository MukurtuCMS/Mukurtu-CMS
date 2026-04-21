<?php

namespace Drupal\geolocation_search_api\Plugin\views\filter;

use Drupal\geolocation\Plugin\views\filter\BoundaryFilter;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Defines a filter for filtering on boundary.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geolocation_search_api_filter_boundary")
 */
class GeolocationSearchApiFilterBoundary extends BoundaryFilter {

  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    /** @var \Drupal\search_api\Plugin\views\query\SearchApiQuery $query */
    $query = $this->getQuery();
    if ($query->shouldAbort()) {
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

    $query->addCondition(
      $this->realField,
      [
        $lat_south_west . ',' . $lng_south_west, $lat_north_east . ',' . $lng_north_east,
      ],
      'BETWEEN',
      $this->options['group']
    );
  }

}

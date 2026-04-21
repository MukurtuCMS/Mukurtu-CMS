<?php

namespace Drupal\geolocation_geometry\Plugin\geolocation\MapCenter;

use Drupal\geolocation\Plugin\geolocation\MapCenter\ViewsBoundaryFilter;

/**
 * Derive center from boundary filter.
 *
 * @MapCenter(
 *   id = "geometry_views_boundary_filter",
 *   name = @Translation("Geometry Boundary filter"),
 *   description = @Translation("Fit map to geometry boundary filter."),
 * )
 */
class GeometryViewsBoundaryFilter extends ViewsBoundaryFilter {

  /**
   * {@inheritdoc}
   */
  public function getAvailableMapCenterOptions($context) {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
      foreach ($displayHandler->getHandlers('filter') as $filter_id => $filter) {
        if ($filter->getPluginId() === 'geolocation_geometry_filter_boundary') {
          // Preserve compatibility to v1.
          $options['boundary_filter_' . $filter_id] = $this->t('Geo Boundary filter') . ' - ' . $filter->adminLabel();
        }
      }
    }

    return $options;
  }

}

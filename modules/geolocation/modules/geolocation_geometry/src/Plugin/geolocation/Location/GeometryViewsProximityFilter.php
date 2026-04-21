<?php

namespace Drupal\geolocation_geometry\Plugin\geolocation\Location;

use Drupal\geolocation\Plugin\geolocation\Location\ViewsProximityFilter;

/**
 * Derive center from proximity filter.
 *
 * @Location(
 *   id = "geometry_views_proximity_filter",
 *   name = @Translation("Geometry Proximity filter"),
 *   description = @Translation("Set map center from geometry proximity filter."),
 * )
 */
class GeometryViewsProximityFilter extends ViewsProximityFilter {

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions($context): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
      foreach ($displayHandler->getHandlers('filter') as $delta => $filter) {
        if (
          $filter->getPluginId() === 'geolocation_geometry_filter_proximity'
          && $filter !== $context
        ) {
          $options[$delta] = $this->t('Geo Proximity filter') . ' - ' . $filter->adminLabel();
        }
      }
    }

    return $options;
  }

}

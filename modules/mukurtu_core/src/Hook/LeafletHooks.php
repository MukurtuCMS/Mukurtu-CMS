<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core Leaflet map styling.
 */
class LeafletHooks {

  /**
   * Implements hook_leaflet_formatter_feature_alter().
   *
   * Removes the shaded fill from line geometries (LineString/
   * MultiLineString) on Leaflet field formatter maps, per issue #732.
   * Polygons keep their configured fill since they represent real areas;
   * circles/circle-markers are styled separately and untouched here
   * (see issue #849).
   */
  #[Hook('leaflet_formatter_feature_alter')]
  public function leafletFormatterFeatureAlter(array &$feature, $item, $entity): void {
    $this->disablePathFillForLines($feature);
  }

  /**
   * Implements hook_leaflet_views_feature_alter().
   *
   * Same line-only fill removal as leafletFormatterFeatureAlter(), applied
   * to Leaflet Views map displays (browse-by-map, etc.).
   */
  #[Hook('leaflet_views_feature_alter')]
  public function leafletViewsFeatureAlter(array &$feature, $result, $rowPlugin): void {
    $this->disablePathFillForLines($feature);
  }

  /**
   * Disables path fill for line-type features only.
   */
  private function disablePathFillForLines(array &$feature): void {
    if (!in_array($feature['type'] ?? NULL, ['linestring', 'multilinestring'], TRUE)) {
      return;
    }

    $path = json_decode($feature['path'] ?? '', TRUE);
    if (!is_array($path)) {
      return;
    }

    $path['fill'] = FALSE;
    $feature['path'] = json_encode($path);
  }

}

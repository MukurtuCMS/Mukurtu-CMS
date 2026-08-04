<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core Leaflet map styling.
 */
class LeafletHooks {

  /**
   * Line-type geometry identifiers used by leaflet/leaflet_views features.
   *
   * Includes 'multipolyline' because \Drupal\leaflet\LeafletService renames
   * a MultiLineString geometry's type to 'multipolyline' (not
   * 'multilinestring') when building the feature array.
   */
  private const LINE_TYPES = ['linestring', 'multilinestring', 'multipolyline'];

  /**
   * Geometry-collection identifiers that nest sub-features under 'component'.
   */
  private const COLLECTION_TYPES = ['geometrycollection', 'multipoint'];

  /**
   * Implements hook_leaflet_formatter_feature_alter().
   *
   * Removes the shaded fill from line geometries on Leaflet field formatter
   * maps, per issue #732. Polygons keep their configured fill since they
   * represent real areas; circles/circle-markers are styled separately and
   * untouched here (see issue #849).
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
   *
   * A geofield value containing more than one line (e.g. several lines
   * drawn into a single map field) is stored as one multi-geometry WKT
   * value. Depending on how it was created, leaflet/LeafletService turns
   * that into either a single 'multipolyline' feature, or a
   * 'geometrycollection'/'multipoint' feature whose individual lines are
   * nested under $feature['component']. Neither of those matches a plain
   * 'linestring' type, so both need their own handling here.
   */
  private function disablePathFillForLines(array &$feature): void {
    $type = $feature['type'] ?? NULL;

    if (in_array($type, self::COLLECTION_TYPES, TRUE) && is_array($feature['component'] ?? NULL)) {
      // Give each line sub-component its own 'path' key so it overrides the
      // shared parent path when rendered (see Drupal.Leaflet.create_collection
      // in leaflet.drupal.js, which spreads the parent feature's properties
      // onto each component before applying path style). Non-line siblings
      // (e.g. a polygon in the same collection) are left untouched so they
      // keep inheriting the parent's original path.
      foreach ($feature['component'] as &$component) {
        $this->disableComponentFillIfLine($component, $feature['path'] ?? NULL);
      }
      unset($component);
      return;
    }

    if (!in_array($type, self::LINE_TYPES, TRUE)) {
      return;
    }

    $feature['path'] = $this->withFillDisabled($feature['path'] ?? NULL);
  }

  /**
   * Applies the line-fill-disable logic to a single collection component.
   *
   * @param array $component
   *   The sub-feature, keyed like a top-level feature but without its own
   *   'path' unless one is set here.
   * @param string|null $parentPath
   *   The path JSON inherited from the enclosing collection feature.
   */
  private function disableComponentFillIfLine(array &$component, ?string $parentPath): void {
    $type = $component['type'] ?? NULL;

    if (in_array($type, self::COLLECTION_TYPES, TRUE) && is_array($component['component'] ?? NULL)) {
      foreach ($component['component'] as &$nested) {
        $this->disableComponentFillIfLine($nested, $parentPath);
      }
      unset($nested);
      return;
    }

    if (!in_array($type, self::LINE_TYPES, TRUE)) {
      return;
    }

    $component['path'] = $this->withFillDisabled($parentPath);
  }

  /**
   * Returns the given path style JSON with 'fill' forced to false.
   *
   * Returns the input unchanged if it isn't valid/decodable JSON.
   */
  private function withFillDisabled(?string $path): ?string {
    $decoded = json_decode($path ?? '', TRUE);
    if (!is_array($decoded)) {
      return $path;
    }

    $decoded['fill'] = FALSE;
    return json_encode($decoded);
  }

}

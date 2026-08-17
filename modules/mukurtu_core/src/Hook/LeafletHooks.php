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
    $this->applyLineFillFix($feature, NULL);
  }

  /**
   * Recursively disables fill on line geometries, including inside
   * geometrycollection/multipoint features.
   *
   * For a collection, it is not enough to give each line component its own
   * fill-disabled 'path' and leave the collection's own 'path' alone:
   * Drupal.Leaflet.create_feature() (leaflet.drupal.js) builds the
   * collection's Leaflet FeatureGroup from its individually-styled
   * components (via create_collection(), which spreads the collection's
   * properties onto each component before styling it), but then immediately
   * calls set_feature_path_style() again on the FeatureGroup itself using
   * the *collection's own* 'path'. L.FeatureGroup.setStyle() cascades that
   * call to every child (including nested collections), which stomps the
   * per-component styling just applied with the collection's shared path -
   * silently re-enabling fill on every line inside it.
   *
   * To prevent that, every component (line or not) is given its own
   * explicit 'path' here, and the collection's own 'path' is then blanked
   * out so that later cascading setStyle() call resolves to an empty style
   * object and becomes a no-op, leaving each component's own styling intact.
   *
   * @param array $feature
   *   The feature (or collection component) being processed.
   * @param string|null $inheritedPath
   *   The path JSON this feature would otherwise inherit from an enclosing
   *   collection, used when $feature has no 'path' of its own.
   */
  private function applyLineFillFix(array &$feature, ?string $inheritedPath): void {
    $type = $feature['type'] ?? NULL;
    $ownPath = $feature['path'] ?? $inheritedPath;

    if (in_array($type, self::COLLECTION_TYPES, TRUE) && is_array($feature['component'] ?? NULL)) {
      foreach ($feature['component'] as &$component) {
        $this->applyLineFillFix($component, $ownPath);
      }
      unset($component);
      $feature['path'] = '';
      return;
    }

    if (in_array($type, self::LINE_TYPES, TRUE)) {
      $feature['path'] = $this->withFillDisabled($ownPath);
      return;
    }

    // Non-line feature nested in a collection (e.g. a polygon): give it an
    // explicit copy of whatever path it would otherwise have inherited, so
    // it keeps rendering as configured once the collection's own path above
    // is blanked out. A non-line feature that isn't part of any collection
    // ($inheritedPath === NULL) is left completely untouched.
    if ($inheritedPath !== NULL && !isset($feature['path'])) {
      $feature['path'] = $inheritedPath;
    }
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

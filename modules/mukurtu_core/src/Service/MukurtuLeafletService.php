<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\leaflet\LeafletService;

/**
 * Decorates leaflet.service to render drawn circles as true circles.
 *
 * GeoJSON has no circle geometry type, so a drawn circle is stored as an
 * N-sided polygon approximation, with the true center/radius stashed as
 * custom circle_center/circle_radius Feature properties (see
 * mukurtu-leaflet-widget.js). LeafletService::leafletProcessGeometry()
 * only ever emits plain lat/lon point lists from the underlying geometry
 * and has no way to carry GeoJSON Feature properties through, so those
 * properties are otherwise lost before reaching the browser and every
 * circle renders on view/browse pages as a faceted polygon instead of a
 * smooth circle. This restores them so the display JS
 * (mukurtu-leaflet-circle-display.js) can rebuild a true, zoom-independent
 * L.circle instead.
 *
 * A single stored geofield value can bundle multiple drawn shapes together
 * in one GeoJSON FeatureCollection (the widget's "drawn items" group
 * serializes as a whole), so shapes are split one-feature-at-a-time before
 * delegating to the parent, and only a feature actually carrying
 * circle_center/circle_radius is swapped for a circle datum; every other
 * shape keeps the parent's normal point/polygon/etc. output.
 */
class MukurtuLeafletService extends LeafletService {

  /**
   * {@inheritdoc}
   */
  public function leafletProcessGeofield($items = []) {
    if (!is_array($items)) {
      $items = [$items];
    }

    $expanded_items = [];
    foreach ($items as $item) {
      array_push($expanded_items, ...$this->splitFeatureCollection($item));
    }

    $data = parent::leafletProcessGeofield($expanded_items);

    // parent::leafletProcessGeofield() silently skips any item its geoPHP
    // wrapper can't parse, so $data can be shorter than $expanded_items.
    // Re-run just the same parse check to keep our index into $data aligned
    // with the item it actually came from.
    $data_index = 0;
    foreach ($expanded_items as $item) {
      $raw = is_array($item) ? ($item['wkt'] ?? $item) : $item;
      if (!$this->geoPhpWrapper->load($raw)) {
        continue;
      }
      if (!array_key_exists($data_index, $data)) {
        break;
      }
      $circle = $this->extractCircleProperties($raw);
      if ($circle) {
        $data[$data_index] = [
          'type' => 'circle',
          'lat' => (float) $circle['center'][0],
          'lon' => (float) $circle['center'][1],
          'radius' => (float) $circle['radius'],
          // L.Circle has a getRadius() method, same as the circleMarker
          // Drupal.Leaflet treats as a clusterable point (see
          // mukurtu-leaflet-markercluster-grouping-fix.js). Without this,
          // a circle on a map with clustering enabled gets handed to
          // L.MarkerClusterGroup.addLayer(), which requires a real
          // L.Marker and throws, breaking the whole map. This flag is an
          // existing, already-checked escape hatch for exactly that case.
          'markercluster_excluded' => TRUE,
        ];
      }
      $data_index++;
    }

    return array_values($data);
  }

  /**
   * Splits a multi-feature GeoJSON FeatureCollection into single-feature ones.
   *
   * @param mixed $item
   *   A single value or array as accepted by leafletProcessGeofield(),
   *   each as a string in any of the supported formats or as an array
   *   with a 'wkt' element.
   *
   * @return array
   *   One or more items in the same shape as $item, each carrying at most
   *   one GeoJSON Feature. Returns [$item] unchanged if it isn't a
   *   multi-feature FeatureCollection.
   */
  private function splitFeatureCollection($item) {
    $raw = is_array($item) ? ($item['wkt'] ?? NULL) : $item;
    if (!is_string($raw)) {
      return [$item];
    }

    $geoJson = json_decode($raw, TRUE);
    if (!is_array($geoJson)
      || ($geoJson['type'] ?? NULL) !== 'FeatureCollection'
      || count($geoJson['features'] ?? []) < 2) {
      return [$item];
    }

    $split = [];
    foreach ($geoJson['features'] as $feature) {
      $single = $geoJson;
      $single['features'] = [$feature];
      $encoded = json_encode($single);
      $split[] = is_array($item) ? ['wkt' => $encoded] + $item : $encoded;
    }
    return $split;
  }

  /**
   * Extracts circle_center/circle_radius from a stored GeoJSON value.
   *
   * @param mixed $raw
   *   A single geofield value in any of the supported formats.
   *
   * @return array|null
   *   ['center' => [lat, lon], 'radius' => radius in meters], or NULL if
   *   $raw isn't a GeoJSON Feature/FeatureCollection carrying those
   *   properties.
   */
  private function extractCircleProperties($raw) {
    if (!is_string($raw)) {
      return NULL;
    }

    $geoJson = json_decode($raw, TRUE);
    if (!is_array($geoJson)) {
      return NULL;
    }

    $type = $geoJson['type'] ?? NULL;
    if ($type === 'FeatureCollection') {
      $features = $geoJson['features'] ?? [];
    }
    elseif ($type === 'Feature') {
      $features = [$geoJson];
    }
    else {
      return NULL;
    }

    foreach ($features as $feature) {
      $properties = $feature['properties'] ?? [];
      if (isset($properties['circle_center'], $properties['circle_radius'])) {
        return [
          'center' => $properties['circle_center'],
          'radius' => $properties['circle_radius'],
        ];
      }
    }

    return NULL;
  }

}

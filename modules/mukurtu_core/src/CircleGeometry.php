<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

/**
 * Converts between a circle and the polygon that represents it in storage.
 *
 * A circle drawn on the map is not stored as a circle. GeoJSON has no circle
 * type, so mukurtu-leaflet-widget.js stores it as a 128 sided polygon carrying
 * circle_center and circle_radius properties, and rebuilds the real circle from
 * those properties when the map loads. This class is the server side of that
 * arrangement, so the accessible lat/long editor can offer a centre and a
 * radius rather than 128 vertex rows.
 *
 * The points are computed geodesically, walking a fixed distance along each
 * bearing from the centre, rather than by projecting to screen pixels the way
 * Leaflet-Geoman does client side. The two agree closely at the scales a
 * coverage map uses and diverge only for very large radii near the poles.
 * Exactness is not required: the polygon is a fallback representation for
 * consumers that cannot draw a circle, and the map itself always rebuilds the
 * true circle from circle_center and circle_radius.
 */
final class CircleGeometry {

  /**
   * Number of sides, matching L.PM.Utils.circleToPolygon(circle, 128).
   */
  public const SIDES = 128;

  /**
   * Mean Earth radius in metres (IUGG).
   */
  private const EARTH_RADIUS = 6371008.8;

  /**
   * Builds the closed polygon ring representing a circle.
   *
   * @param float $lat
   *   Centre latitude in degrees.
   * @param float $lon
   *   Centre longitude in degrees.
   * @param float $radius
   *   Radius in metres.
   * @param int $sides
   *   Number of sides to approximate with.
   *
   * @return array
   *   A GeoJSON Polygon coordinates value: a single ring of [lon, lat] pairs
   *   whose last point repeats the first.
   */
  public static function toPolygonCoordinates(float $lat, float $lon, float $radius, int $sides = self::SIDES): array {
    $sides = max(3, $sides);
    $latRad = deg2rad($lat);
    $lonRad = deg2rad($lon);
    $angular = $radius / self::EARTH_RADIUS;

    $ring = [];
    for ($i = 0; $i < $sides; $i++) {
      $bearing = 2 * M_PI * $i / $sides;

      $pointLat = asin(
        sin($latRad) * cos($angular) +
        cos($latRad) * sin($angular) * cos($bearing)
      );
      $pointLon = $lonRad + atan2(
        sin($bearing) * sin($angular) * cos($latRad),
        cos($angular) - sin($latRad) * sin($pointLat)
      );

      // Keep longitudes in -180..180 so the ring does not carry values a
      // consumer would reject after crossing the antimeridian.
      $degLon = fmod(rad2deg($pointLon) + 540.0, 360.0) - 180.0;

      $ring[] = [
        round($degLon, 6),
        round(rad2deg($pointLat), 6),
      ];
    }

    // GeoJSON requires the ring to close.
    $ring[] = $ring[0];

    return [$ring];
  }

  /**
   * Reads the circle a feature describes, if it describes one.
   *
   * @param array $feature
   *   A GeoJSON Feature.
   *
   * @return array|null
   *   ['lat' => float, 'lon' => float, 'radius' => float], or NULL if the
   *   feature is not a circle or its metadata is unusable.
   */
  public static function fromFeature(array $feature): ?array {
    $properties = $feature['properties'] ?? NULL;
    if (!is_array($properties)) {
      return NULL;
    }

    $center = $properties['circle_center'] ?? NULL;
    $radius = $properties['circle_radius'] ?? NULL;

    // The widget writes the centre as [lat, lng], which is Leaflet's order and
    // the reverse of GeoJSON's. Accept the object form too, since a hand
    // edited or third party feature may use it.
    if (is_array($center) && array_key_exists(0, $center) && array_key_exists(1, $center)) {
      $lat = $center[0];
      $lon = $center[1];
    }
    elseif (is_array($center) && isset($center['lat'])) {
      $lat = $center['lat'];
      $lon = $center['lng'] ?? $center['lon'] ?? NULL;
    }
    else {
      return NULL;
    }

    if (!is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)) {
      return NULL;
    }
    if ((float) $radius <= 0.0) {
      // A zero or negative radius is not a circle anyone can edit; leave the
      // shape to be handled as an ordinary polygon.
      return NULL;
    }

    return [
      'lat' => (float) $lat,
      'lon' => (float) $lon,
      'radius' => (float) $radius,
    ];
  }

  /**
   * Writes circle metadata back onto a feature's properties.
   */
  public static function applyToFeature(array $feature, float $lat, float $lon, float $radius): array {
    $properties = is_array($feature['properties'] ?? NULL) ? $feature['properties'] : [];
    $properties['circle_center'] = [$lat, $lon];
    $properties['circle_radius'] = $radius;

    $feature['type'] = 'Feature';
    $feature['properties'] = $properties;
    $feature['geometry'] = [
      'type' => 'Polygon',
      'coordinates' => self::toPolygonCoordinates($lat, $lon, $radius),
    ];

    return $feature;
  }

}

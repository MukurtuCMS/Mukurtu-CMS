<?php

namespace Drupal\geofield;

/**
 * Helper class that generates WKT format geometries.
 */
class WktGenerator implements WktGeneratorInterface {

  /**
   * Helper to generate DD coordinates.
   *
   * @param int $min
   *   The minimum value available to return.
   * @param int $max
   *   The minimum value available to return.
   * @param bool $int
   *   Force to return an integer value. Defaults to FALSE.
   *
   * @return float|int
   *   The coordinate component.
   */
  protected function ddGenerate($min, $max, $int = FALSE) {
    $func = 'rand';
    if (function_exists('mt_rand')) {
      $func = 'mt_rand';
    }
    $number = $func($min, $max);
    if ($int || $number === $min || $number === $max) {
      return $number;
    }
    $decimals = $func(1, pow(10, 5)) / pow(10, 5);
    return round($number + $decimals, 5);
  }

  /**
   * {@inheritdoc}
   */
  public function wktGenerateGeometry() {
    $types = [
      GEOFIELD_TYPE_POINT,
      GEOFIELD_TYPE_MULTIPOINT,
      GEOFIELD_TYPE_LINESTRING,
      GEOFIELD_TYPE_MULTILINESTRING,
      GEOFIELD_TYPE_POLYGON,
      GEOFIELD_TYPE_MULTIPOLYGON,
    ];
    // Don't always generate the same type.
    shuffle($types);
    $type = $types[0];
    $func = 'WktGenerate' . ucfirst($type);
    if (method_exists($this, $func)) {
      return $this->$func();
    }
    return 'POINT (0 0)';
  }

  /**
   * Generates a random coordinates array.
   *
   * @return array
   *   A Lon, Lat array
   */
  protected function randomPoint() {
    $lon = $this->ddGenerate(-180, 180);
    $lat = $this->ddGenerate(-84, 84);
    return [$lon, $lat];
  }

  /**
   * Generates a WKT string given a feature type and some coordinates.
   *
   * @param string $type
   *   The Geo feature type.
   * @param string $value
   *   The coordinates to include.
   *
   * @return string
   *   The WKT value.
   */
  protected function buildWkt($type, $value) {
    return strtoupper($type) . ' (' . $value . ')';
  }

  /**
   * Builds a multi-geometry coordinates string given an array of features.
   *
   * @param array $coordinates
   *   The coordinates to generate the multi-geometry.
   *
   * @return string
   *   The multi-geometry coordinates string.
   */
  protected function buildMultiCoordinates(array $coordinates) {
    return '(' . implode('), (', $coordinates) . ')';
  }

  /**
   * Generates a point coordinates.
   *
   * @param array $point
   *   A Lon Lat array.
   *
   * @return string
   *   The structured point coordinates.
   */
  protected function buildPoint(array $point) {
    return implode(' ', $point);
  }

  /**
   * {@inheritdoc}
   */
  public function wktGeneratePoint(?array $point = NULL) {
    $point = $point ? $point : $this->randomPoint();
    return $this->wktBuildPoint($point);
  }

  /**
   * {@inheritdoc}
   */
  public function wktBuildPoint(array $point) {
    return $this->buildWkt(GEOFIELD_TYPE_POINT, $this->buildPoint($point));
  }

  /**
   * Generates a multipoint coordinates.
   *
   * @return string
   *   The structured multipoint coordinates.
   */
  protected function generateMultipoint() {
    $num = $this->ddGenerate(1, 5, TRUE);
    $start = $this->randomPoint();
    $points[] = $this->buildPoint($start);
    for ($i = 0; $i < $num; $i += 1) {
      $diff = $this->randomPoint();
      $start[0] += $diff[0] / 100;
      $start[1] += $diff[1] / 100;
      $points[] = $this->buildPoint($start);
    }
    return $this->buildMultiCoordinates($points);
  }

  /**
   * {@inheritdoc}
   */
  public function wktGenerateMultipoint() {
    return $this->buildWkt(GEOFIELD_TYPE_MULTIPOINT, $this->generateMultipoint());
  }

  /**
   * Generates a linestring components array.
   *
   * @param array $start
   *   The starting point. If not provided, will be randomly generated.
   * @param int $segments
   *   Number of segments. If not provided, will be randomly generated.
   *
   * @return array
   *   The linestring components coordinates.
   */
  protected function generateLinestring(?array $start = NULL, $segments = NULL) {
    $start = $start ? $start : $this->randomPoint();
    $segments = $segments ? $segments : $this->ddGenerate(2, 5, TRUE);
    $points[] = [$start[0], $start[1]];
    // Points are at most 1km away from each other.
    for ($i = 1; $i < $segments; $i += 1) {
      $diff = $this->randomPoint();
      $start[0] += $diff[0] / 100;
      $start[1] += $diff[1] / 100;
      $points[] = [$start[0], $start[1]];
    }
    return $points;
  }

  /**
   * {@inheritdoc}
   */
  public function wktGenerateLinestring(?array $start = NULL, $segments = NULL) {
    return $this->wktBuildLinestring($this->generateLinestring($start, $segments));
  }

  /**
   * Builds a Linestring format string from an array of point components.
   *
   * @param array $points
   *   Array containing the linestring component's coordinates.
   *
   * @return string
   *   The structured linestring coordinates.
   */
  protected function buildLinestring(array $points) {
    $components = [];
    foreach ($points as $point) {
      $components[] = $this->buildPoint($point);
    }
    return implode(", ", $components);
  }

  /**
   * {@inheritdoc}
   */
  public function wktBuildLinestring(array $points) {
    return $this->buildWkt(GEOFIELD_TYPE_LINESTRING, $this->buildLinestring($points));
  }

  /**
   * Generates a multilinestring coordinates.
   *
   * @return string
   *   The structured multilinestring coordinates.
   */
  protected function generateMultilinestring() {
    $start = $this->randomPoint();
    $num = $this->ddGenerate(1, 3, TRUE);
    $lines[] = $this->buildLinestring($this->generateLinestring($start));
    for ($i = 0; $i < $num; $i += 1) {
      $diff = $this->randomPoint();
      $start[0] += $diff[0] / 100;
      $start[1] += $diff[1] / 100;
      $lines[] = $this->buildLinestring($this->generateLinestring($start));
    }
    return $this->buildMultiCoordinates($lines);
  }

  /**
   * {@inheritdoc}
   */
  public function wktGenerateMultilinestring() {
    return $this->buildWkt(GEOFIELD_TYPE_MULTILINESTRING, $this->generateMultilinestring());
  }

  /**
   * Generates a polygon components array.
   *
   * @param array $start
   *   The starting point. If not provided, will be randomly generated.
   * @param int $segments
   *   Number of segments. If not provided, will be randomly generated.
   *
   * @return array
   *   The polygon components coordinates.
   */
  protected function generatePolygon(?array $start = NULL, $segments = NULL) {
    $start = $start ?: $this->randomPoint();
    $segments = $segments ?: $this->ddGenerate(2, 4, TRUE);
    $poly = $this->generateLinestring($start, $segments);
    // Close the polygon.
    $poly[] = $start;
    return $poly;
  }

  /**
   * {@inheritdoc}
   */
  public function wktGeneratePolygon(?array $start = NULL, $segments = NULL) {
    return $this->wktBuildPolygon($this->generatePolygon($start, $segments));
  }

  /**
   * Builds a polygon format string from an array of point components.
   *
   * @param array $points
   *   Array containing the polygon components coordinates.
   *
   * @return string
   *   The structured polygon coordinates.
   */
  protected function buildPolygon(array $points) {
    $components = [];
    foreach ($points as $point) {
      $components[] = $this->buildPoint($point);
    }
    return '(' . implode(", ", $components) . ')';
  }

  /**
   * {@inheritdoc}
   */
  public function wktBuildPolygon(array $points) {
    return $this->buildWkt(GEOFIELD_TYPE_POLYGON, $this->buildPolygon($points));
  }

  /**
   * Generates a multipolygon coordinates.
   *
   * @return string
   *   The structured multipolygon coordinates.
   */
  protected function generateMultipolygon() {
    $start = $this->randomPoint();
    $num = $this->ddGenerate(1, 5, TRUE);
    $segments = $this->ddGenerate(2, 3, TRUE);
    $poly[] = $this->buildPolygon($this->generatePolygon($start, $segments));
    for ($i = 0; $i < $num; $i += 1) {
      $diff = $this->randomPoint();
      $start[0] += $diff[0] / 100;
      $start[1] += $diff[1] / 100;
      $poly[] = $this->buildPolygon($this->generatePolygon($start, $segments));
    }
    return $this->buildMultiCoordinates($poly);
  }

  /**
   * {@inheritdoc}
   */
  public function wktGenerateMultipolygon() {
    return $this->buildWkt(GEOFIELD_TYPE_MULTIPOLYGON, $this->generateMultipolygon());
  }

  /**
   * Builds a multipolygon coordinates.
   *
   * @param array $rings
   *   The array of polygon arrays.
   *
   * @return string
   *   The structured multipolygon coordinates.
   */
  protected function buildMultipolygon(array $rings) {
    $poly = [];
    foreach ($rings as $ring) {
      $poly[] = $this->buildPolygon($ring);
    }
    return $this->buildMultiCoordinates($poly);
  }

  /**
   * {@inheritdoc}
   */
  public function wktBuildMultipolygon(array $rings) {
    return $this->buildWkt(GEOFIELD_TYPE_MULTIPOLYGON, $this->buildMultipolygon($rings));
  }

}

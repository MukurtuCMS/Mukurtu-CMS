<?php

namespace Drupal\geofield;

/**
 * Defines an interface for WktGenerator.
 */
interface WktGeneratorInterface {

  /**
   * Helper to generate a random WKT string.
   *
   * Try to keeps values sane, no shape is more than 100km across.
   *
   * @return string
   *   The random WKT value.
   */
  public function wktGenerateGeometry();

  /**
   * Returns a WKT format point feature given a point.
   *
   * @param array $point
   *   The point coordinates.
   *
   * @return string
   *   The WKT point feature.
   */
  public function wktBuildPoint(array $point);

  /**
   * Returns a WKT format point feature.
   *
   * @param array $point
   *   A Lon Lat array. By default, create a random pair.
   *
   * @return string
   *   The WKT point feature.
   */
  public function wktGeneratePoint(?array $point = NULL);

  /**
   * Returns a WKT format multipoint feature.
   *
   * @return string
   *   The WKT multipoint feature.
   */
  public function wktGenerateMultipoint();

  /**
   * Returns a WKT format linestring feature given an array of points.
   *
   * @param array $points
   *   The linestring components.
   *
   * @return string
   *   The WKT linestring feature.
   */
  public function wktBuildLinestring(array $points);

  /**
   * Returns a WKT format linestring feature.
   *
   * @param array $start
   *   The starting point. If not provided, will be randomly generated.
   * @param int $segments
   *   Number of segments. If not provided, will be randomly generated.
   *
   * @return string
   *   The WKT linestring feature.
   */
  public function wktGenerateLinestring(?array $start = NULL, $segments = NULL);

  /**
   * Returns a WKT format multilinestring feature.
   *
   * @return string
   *   The WKT multilinestring feature.
   */
  public function wktGenerateMultilinestring();

  /**
   * Returns a WKT format polygon feature given an array of points.
   *
   * @param array $points
   *   The polygon components.
   *
   * @return string
   *   The WKT polygon feature.
   */
  public function wktBuildPolygon(array $points);

  /**
   * Returns a WKT format polygon feature.
   *
   * @param array $start
   *   The starting point. If not provided, will be randomly generated.
   * @param int $segments
   *   Number of segments. If not provided, will be randomly generated.
   *
   * @return string
   *   The WKT polygon feature.
   */
  public function wktGeneratePolygon(?array $start = NULL, $segments = NULL);

  /**
   * Returns a WKT format multipolygon feature given an array of polygon points.
   *
   * @param array $rings
   *   The array of polygon arrays.
   *
   * @return string
   *   The WKT multipolygon feature.
   */
  public function wktBuildMultipolygon(array $rings);

  /**
   * Returns a WKT format multipolygon feature.
   *
   * @return string
   *   The WKT multipolygon feature.
   */
  public function wktGenerateMultipolygon();

}

<?php

namespace Drupal\geolocation_geometry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for geospatial values.
 *
 * @Constraint(
 *   id = "GeometryType",
 *   label = @Translation("Geometry data valid for geofield type.", context = "Validation"),
 * )
 */
class GeometryConstraint extends Constraint {

  /**
   * Message for type issue.
   *
   * @var string
   *   Message.
   */
  public $messageType = '"@value" is not a valid @type.';

  /**
   * Message for Geometry issue.
   *
   * @var string
   *   Message.
   */
  public $messageGeom = '"@value" is not a valid geospatial content for @geom_type geometry.';

  /**
   * Data type.
   *
   * @var string
   *   Type.
   */
  public $type = 'wkt';

  /**
   * Geometry type.
   *
   * @var string
   *   Geometry type.
   */
  public $geometryType = 'geometry';

}

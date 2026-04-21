<?php

namespace Drupal\geofield\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for geospatial values.
 *
 * @Constraint(
 *   id = "GeoType",
 *   label = @Translation("Geo data valid for geofield type.", context = "Validation"),
 * )
 */
class GeoConstraint extends Constraint {

  /**
   * Message for invalid value.
   *
   * @var string
   */
  public $message = '"@value" is not a valid geospatial content.';

}

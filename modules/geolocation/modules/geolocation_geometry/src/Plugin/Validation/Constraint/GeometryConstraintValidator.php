<?php

namespace Drupal\geolocation_geometry\Plugin\Validation\Constraint;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the GeoType constraint.
 */
class GeometryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {

    if (!is_a($constraint, GeometryConstraint::class)) {
      return;
    }

    if (isset($value)) {
      try {
        $query = NULL;
        /* maybe: this could be configurable with field options */
        $allowed_types_for_geometry = [
          'point',
          'multipoint',
          'linestring',
          'multilinestring',
          'polygon',
          'multipolygon',
          'geometrycollection',
        ];

        if ($constraint->type === 'WKT') {
          $query = \Drupal::database()->query("SELECT ST_GeometryType(ST_GeomFromText(:wkt, 4326)) as type", [':wkt' => $value]);
        }
        elseif ($constraint->type === 'GeoJSON') {
          $query = \Drupal::database()->query("SELECT ST_GeometryType(ST_GeomFromGeoJSON(:json)) as type", [':json' => $value]);
        }

        $result_ = $query->fetchAll();
        $result = str_replace("st_", "", strtolower($result_[0]->type));

        if ($constraint->geometryType != 'geometry' && $result != $constraint->geometryType) {
          $this->context->addViolation($constraint->messageGeom, [
            '@value' => $value,
            '@geom_type' => $constraint->geometryType,
          ]);
        }
        elseif ($constraint->geometryType === 'geometry' && !in_array($result, $allowed_types_for_geometry)) {
          $this->context->addViolation($constraint->messageGeom, [
            '@value' => $value,
            '@geom_type' => $constraint->geometryType,
          ]);
        }
      }
      catch (DatabaseExceptionWrapper $e) {
        $this->context->addViolation($constraint->messageType, [
          '@value' => $value,
          '@type' => $constraint->type,
        ]);
      }
    }
  }

}

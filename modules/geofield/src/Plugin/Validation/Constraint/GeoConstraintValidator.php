<?php

namespace Drupal\geofield\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the GeoType constraint.
 */
class GeoConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * Constructs a new GeoConstraintValidator object.
   *
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   */
  public function __construct(GeoPHPInterface $geophp_wrapper) {
    $this->geoPhpWrapper = $geophp_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (isset($value)) {
      $valid_geometry = TRUE;

      try {
        if (!$this->geoPhpWrapper->load($value)) {
          $valid_geometry = FALSE;
        }
      }
      catch (\Exception $e) {
        $valid_geometry = FALSE;
      }

      if (!$valid_geometry) {
        $this->context->addViolation($constraint->message, ['@value' => $value]);
      }
    }
  }

}

<?php

namespace Drupal\Tests\geofield\Kernel;

use Drupal\Core\TypedData\Validation\ExecutionContext;
use Drupal\geofield\GeoPHP\GeoPHPWrapper;
use Drupal\geofield\Plugin\Validation\Constraint\GeoConstraint;
use Drupal\geofield\Plugin\Validation\Constraint\GeoConstraintValidator;
use Drupal\KernelTests\KernelTestBase;

if (!class_exists('\Drupal\Core\TypedData\Validation\ExecutionContext')) {
  class_alias('\Drupal\Core\Validation\ExecutionContext', '\Drupal\Core\TypedData\Validation\ExecutionContext');
}

/**
 * Tests geofield constraints.
 *
 * @group geofield
 */
class ConstraintsTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['geofield'];

  /**
   * Tests GeoType constraint.
   *
   * @covers \Drupal\geofield\Plugin\Validation\Constraint\GeoConstraintValidator
   * @covers \Drupal\geofield\Plugin\Validation\Constraint\GeoConstraint
   *
   * @dataProvider geoProvider
   */
  public function testGeoConstraint($coordinates, $expected_violation_count) {
    // Check message in constraint.
    $constraint = new GeoConstraint();
    $this->assertEquals('"@value" is not a valid geospatial content.', $constraint->message, 'Correct constraint message found.');

    $execution_context = $this->createMock(ExecutionContext::class);

    if ($expected_violation_count) {
      $execution_context->expects($this->exactly($expected_violation_count))
        ->method('addViolation')
        ->with($constraint->message, ['@value' => $coordinates]);
    }
    else {
      $execution_context->expects($this->exactly($expected_violation_count))
        ->method('addViolation');
    }

    $geophp_wrapper = new GeoPHPWrapper();
    $validator = new GeoConstraintValidator($geophp_wrapper);
    $validator->initialize($execution_context);

    $validator->validate($coordinates, $constraint);
  }

  /**
   * Provides test data for testGeoConstraint().
   */
  public static function geoProvider(): array {
    return [
      'valid POINT' => ['POINT (40 -3)', 0],
      'invalid POAINT' => ['POAINT (40 -3)', 1],
      'invalid POINT' => ['POINT (40 -A)', 1],
    ];
  }

}

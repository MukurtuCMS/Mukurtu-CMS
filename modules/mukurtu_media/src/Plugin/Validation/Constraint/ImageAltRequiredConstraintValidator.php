<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that alt text is provided when an image is uploaded.
 */
final class ImageAltRequiredConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    // Get the parent field item to check if an image is uploaded.
    $item = $this->context->getObject()->getParent();

    // Only validate if there's an image uploaded (target_id is set).
    // This mirrors the form-level validation behavior.
    if (!empty($item->target_id) && (is_null($value) || $value === '')) {
      $this->context->buildViolation($constraint->message)
        ->setInvalidValue($value)
        ->addViolation();
    }
  }

}

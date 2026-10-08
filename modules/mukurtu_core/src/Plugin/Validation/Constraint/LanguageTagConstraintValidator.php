<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MukurtuLanguageTag constraint.
 */
class LanguageTagConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof LanguageTagConstraint);

    // Surrounding spaces are trimmed on save (see LanguageCodeHooks), so
    // they are not an error.
    if (is_string($value)) {
      $value = trim($value);
    }
    if ($value === NULL || $value === '') {
      return;
    }

    if (!is_string($value) || !preg_match(LanguageTagConstraint::PATTERN, $value)) {
      $this->context->addViolation($constraint->message, ['@value' => (string) $value]);
    }
  }

}

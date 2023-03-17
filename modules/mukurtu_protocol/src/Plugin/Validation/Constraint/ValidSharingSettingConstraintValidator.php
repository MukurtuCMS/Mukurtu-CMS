<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidSharingSettingConstraint constraint.
 */
class ValidSharingSettingConstraintValidator extends ConstraintValidator
{
  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!in_array($items, ['any', 'all'])) {
      $this->context->addViolation($constraint->validSharingSettingRequired);
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MukurtuSingleParent constraint.
 */
class MukurtuSingleParentValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    $parents = 0;
    foreach ($items as $item) {
      // A target ID of 0 means the top level, not a parent.
      if (!empty($item->target_id)) {
        $parents++;
      }
    }

    if ($parents > 1) {
      /** @var \Drupal\mukurtu_taxonomy\Plugin\Validation\Constraint\MukurtuSingleParent $constraint */
      $this->context->addViolation($constraint->message);
    }
  }

}

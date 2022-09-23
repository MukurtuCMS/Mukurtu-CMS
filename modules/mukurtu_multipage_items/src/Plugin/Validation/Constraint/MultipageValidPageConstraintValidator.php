<?php

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MultipageValidPageConstraintValidator constraint.
 */
class MultipageValidPageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\mukurtu_multipage_items\Entity\MultipageItem $entity */
    $entity = $items->getEntity();


    //dpm($items);

/*     if (!$inheritanceTarget->access('update')) {
      $this->context->addViolation($constraint->insufficientUserRights);
    }

    if ($controlledEntity->id() == $inheritanceTarget->id()) {
      $this->context->addViolation($constraint->circularReference);
    } */
  }

}

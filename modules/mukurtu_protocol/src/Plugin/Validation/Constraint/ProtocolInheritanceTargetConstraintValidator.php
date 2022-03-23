<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProtocolInheritanceTargetConstraint constraint.
 */
class ProtocolInheritanceTargetConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity */
    $entity = $items->getEntity();

    /** @var \Drupal\Core\Entity\EntityIterface $inheritanceTarget */
    $inheritanceTarget = $entity->getInheritanceTarget();

    $controlledEntity = $entity->getControlledEntity();

    if (!$inheritanceTarget) {
      return;
    }

    if (!$inheritanceTarget->access('update')) {
      $this->context->addViolation($constraint->insufficientUserRights);
    }

    if ($controlledEntity->id() == $inheritanceTarget->id()) {
      $this->context->addViolation($constraint->circularReference);
    }
  }

}

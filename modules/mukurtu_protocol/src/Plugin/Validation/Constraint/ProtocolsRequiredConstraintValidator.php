<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProtocolsRequiredConstraint constraint.
 */
class ProtocolsRequiredConstraintValidator extends ConstraintValidator {
  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity */
    $entity = $items->getEntity();
    $protocols = $entity->getProtocols();
    if (empty($protocols)) {
      $this->context->addViolation($constraint->protocolsRequired);
    }
  }

}

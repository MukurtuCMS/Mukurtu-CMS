<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates protocol inheritance target.
 *
 * @Constraint(
 *   id = "ProtocolInheritanceTargetConstraint",
 *   label = @Translation("Protocol Inheritance Target Validation", context = "Validation"),
 *   type = "string"
 * )
 */
class ProtocolInheritanceTargetConstraint extends Constraint {
  public $circularReference = 'Content cannot inherit protocols from itself.';
  public $insufficientUserRights = 'Protocols can only be inherited from targets in which the user has permission to use the protocols of the target content.';
}

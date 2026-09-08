<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates protocol inheritance target.
 */
#[Constraint(
  id: 'ProtocolInheritanceTargetConstraint',
  label: new TranslatableMarkup('Protocol Inheritance Target Validation', [], ['context' => 'Validation']),
  type: 'string',
)]
class ProtocolInheritanceTargetConstraint extends SymfonyConstraint {
  public $circularReference = 'Content cannot inherit protocols from itself.';
  public $insufficientUserRights = 'Protocols can only be inherited from targets in which the user has permission to use the protocols of the target content.';
}

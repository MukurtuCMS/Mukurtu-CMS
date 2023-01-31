<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates protocols for Protocol control entities.
 *
 * @Constraint(
 *   id = "ProtocolsRequiredConstraint",
 *   label = @Translation("Protocol Control - Protocols Required", context = "Validation"),
 *   type = "string"
 * )
 */
class ProtocolsRequiredConstraint extends Constraint {
  public $protocolsRequired = 'There must be at least one Cultural Protocol selected.';
}

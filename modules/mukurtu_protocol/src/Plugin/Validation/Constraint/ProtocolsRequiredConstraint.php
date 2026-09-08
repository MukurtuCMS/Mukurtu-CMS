<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates protocols for Protocol control entities.
 */
#[Constraint(
  id: 'ProtocolsRequiredConstraint',
  label: new TranslatableMarkup('Protocol Control - Protocols Required', [], ['context' => 'Validation']),
  type: 'string',
)]
class ProtocolsRequiredConstraint extends SymfonyConstraint {
  public $protocolsRequired = 'There must be at least one Cultural Protocol selected.';
}

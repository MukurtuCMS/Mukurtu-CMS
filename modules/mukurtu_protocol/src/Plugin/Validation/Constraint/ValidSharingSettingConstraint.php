<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates sharing setting for cultural protocol fields.
 */
#[Constraint(
  id: 'ValidSharingSettingConstraint',
  label: new TranslatableMarkup('Valid Cultural Protocol Sharing Setting Constraint', [], ['context' => 'Validation']),
  type: 'string',
)]
class ValidSharingSettingConstraint extends SymfonyConstraint {
  public $validSharingSettingRequired = 'The cultural protocols sharing setting must be one of "any" or "all".';
}

<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates sharing setting for cultural protocol fields.
 *
 * @Constraint(
 *   id = "ValidSharingSettingConstraint",
 *   label = @Translation("Valid Cultural Protocol Sharing Setting Constraint", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidSharingSettingConstraint extends Constraint {
  public $validSharingSettingRequired = 'The cultural protocols sharing setting must be one of "any" or "all".';
}

<?php

namespace Drupal\mukurtu_community_records\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid community record.
 *
 * @Constraint(
 *   id = "ValidCommunityRecord",
 *   label = @Translation("Valid Community Record", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidCommunityRecord extends Constraint {
  public $circularReference = 'An item cannot be self referential';
}

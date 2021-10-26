<?php

namespace Drupal\mukurtu_community_records\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid original record.
 *
 * @Constraint(
 *   id = "ValidOriginalRecord",
 *   label = @Translation("Valid Original Record", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidOriginalRecord extends Constraint {
  public $circularReference = 'An item %title (%id) cannot be its own community record.';
  public $nestedCommunityRecord = 'The item %title (%id) cannot be a community record.';
  public $invalidTargetNoAccess = 'The item ID %id is not a valid original record target.';
  public $invalidTargetAccess = '%title (%id) is already a community record for another item and cannot be attached to another.';
}

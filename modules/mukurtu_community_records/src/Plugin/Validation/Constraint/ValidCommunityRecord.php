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
  public $circularReference = 'An item %title (%id) cannot be its own community record.';
  public $nestedCommunityRecord = 'The community record %title (%id) cannot have a community record attached.';
  public $invalidTargetNoAccess = 'The ID %id does not represent a valid community record target.';
  public $invalidTargetAccess = '%title (%id) is already a community record for another item and cannot be attached to another.';
}

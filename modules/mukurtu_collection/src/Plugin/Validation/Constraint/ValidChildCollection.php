<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid child collection.
 *
 * @Constraint(
 *   id = "ValidChildCollection",
 *   label = @Translation("Valid Child Collection", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidChildCollection extends Constraint {
  public $invalidChildCollectionTargetSelf = 'A collection cannot be its own sub-collection.';
  public $invalidChildCollectionTargetUnavailable = 'Collection ID %target_id is already part of a collection hierarchy and cannot be used in another.';
}

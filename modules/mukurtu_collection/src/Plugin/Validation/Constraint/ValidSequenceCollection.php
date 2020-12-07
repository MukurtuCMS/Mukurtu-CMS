<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid community record.
 *
 * @Constraint(
 *   id = "ValidSequenceCollection",
 *   label = @Translation("Valid Sequence Collection", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidSequenceCollection extends Constraint {
  public $invalidSequenceCollectionTargetNonExistence = 'There is no collection with the given ID: @target_id';
  public $invalidSequenceCollectionTargetNonMember = 'This item is not in the collection (@target_id). New items can only be added to a multipage item by editing the owning collection.';
}

<?php

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the node to be added to MPI pages is valid.
 *
 * @Constraint(
 *   id = "MultipageValidNode",
 *   label = @Translation("Multipage Valid Node", context = "Validation"),
 *   type = "string"
 * )
 */
class MultipageValidNodeConstraint extends Constraint {

  // The message that will be shown if the node is already in an MPI.
  public $alreadyInMPI = '%value is already contained in an existing multipage item.';

  // The message that will be shown if the node is a community record.
  public $isCommunityRecord = '%value is a community record.';

  // The message that will be shown if the node is of a bundle type not enabled
  // for use as an MPI.
  public $notEnabledBundleType = '%value is not of bundle type enabled for multipage items.';

}

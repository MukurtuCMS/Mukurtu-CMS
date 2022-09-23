<?php

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates protocol inheritance target.
 *
 * @Constraint(
 *   id = "MultipageValidPageConstraint",
 *   label = @Translation("Multipage Valid Page Validation", context = "Validation"),
 *   type = "string"
 * )
 */
class MultipageValidPageConstraint extends Constraint {
  public $existingPage = 'Content can only be contained in a single multipage item.';
  //public $insufficientUserRights = 'Protocols can only be inherited from targets in which the user has permission to use the protocols of the target content.';
}

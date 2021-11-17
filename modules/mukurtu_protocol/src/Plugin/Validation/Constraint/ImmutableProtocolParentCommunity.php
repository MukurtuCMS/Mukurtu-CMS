<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the protocol's parent community has not changed.
 *
 * @Constraint(
 *   id = "ImmutableProtocolParentCommunity",
 *   label = @Translation("Immutable Protocol Parent Community", context = "Validation"),
 *   type = "string"
 * )
 */
class ImmutableProtocolParentCommunity extends Constraint {
  public $parentCommunityChange = 'A protocol cannot change parent communities after the parent community has been set.';
}

<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid collection item.
 *
 * @Constraint(
 *   id = "ValidCollectionItem",
 *   label = @Translation("Valid Collection Item", context = "Validation"),
 *   type = "string"
 * )
 */
class ValidCollectionItem extends Constraint {
  public $invalidCollectionItemSelfReference = 'A collection cannot contain itself.';
  public $invalidCollectionItemDuplicate = 'A collection cannot contain duplicates: @item.';
}

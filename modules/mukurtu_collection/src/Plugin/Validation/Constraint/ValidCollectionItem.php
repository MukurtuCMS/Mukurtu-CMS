<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the submitted value is a valid collection item.
 */
#[Constraint(
  id: 'ValidCollectionItem',
  label: new TranslatableMarkup('Valid Collection Item', [], ['context' => 'Validation']),
  type: 'string',
)]
class ValidCollectionItem extends SymfonyConstraint {
  public $invalidCollectionItemSelfReference = 'A collection cannot contain itself.';
  public $invalidCollectionItemDuplicate = 'A collection cannot contain duplicates: @item.';
}

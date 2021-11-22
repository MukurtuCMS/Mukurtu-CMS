<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidCollectionItem constraint.
 */
class ValidCollectionItemValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Get the owning entity and its ID.
    $entity = $items->getEntity();

    $refs = [];
    foreach ($items as $item) {
      // No circular references allowed.
      if ($item->target_id == $entity->id()) {
        $this->context->addViolation($constraint->invalidCollectionItemSelfReference);
      }

      // Check for duplicates.
      if (in_array($item->target_id, $refs)) {
        $entity = \Drupal::entityTypeManager()->getStorage('node')->load($item->target_id);
        $title = $entity->getTitle() ?? '';
        $this->context->addViolation($constraint->invalidCollectionItemDuplicate, ['@item' => $title]);
      }
      $refs[] = $item->target_id;
    }
  }

}

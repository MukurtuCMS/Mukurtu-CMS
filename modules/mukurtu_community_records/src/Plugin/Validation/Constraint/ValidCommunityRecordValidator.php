<?php

namespace Drupal\mukurtu_community_records\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidCommunityRecord constraint.
 */
class ValidCommunityRecordValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Get the owning entity and its ID.
    $entity = $items->getEntity();
    $entity_id = $entity->id();

    foreach ($items as $item) {
      // Are we trying to set a circular reference?
      $target_id = $item->target_id;
      if ($target_id == $entity_id) {
        $this->context->addViolation($constraint->circularReference, ['%value' => $item->value]);
      }
    }
  }
}

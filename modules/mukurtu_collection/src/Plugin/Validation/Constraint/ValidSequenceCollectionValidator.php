<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidSequenceCollection constraint.
 */
class ValidSequenceCollectionValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Get the owning entity and its ID.
    $entity = $items->getEntity();
    $entity_id = $entity->id();

    foreach ($items as $item) {
      $target_id = $item->target_id;
      if ($target_id) {
        $collection = \Drupal::entityTypeManager()->getStorage('node')->load($target_id);
        // Check if the collection exists.
        if (!is_null($collection) && $collection->bundle() === 'collection') {
          // Get the items in the collection, the content needs to be in the
          // collection before it can be added to the content's sequence
          // collection field.
          $items = mukurtu_core_flatten_entity_ref_field($collection, MUKURTU_COLLECTION_FIELD_NAME_ITEMS);
          if (!in_array($entity_id, $items)) {
            // The content was not in the collection.
            $this->context->addViolation($constraint->invalidSequenceCollectionTargetNonMember, ['@target_id' => $item->target_id]);
          }
        } else {
          // A collection with that ID does not exist.
          $this->context->addViolation($constraint->invalidSequenceCollectionTargetNonExistence, ['@target_id' => $item->target_id]);
        }
      } else {
        // A collection with that ID does not exist.
        $this->context->addViolation($constraint->invalidSequenceCollectionTargetNonExistence, ['@target_id' => $item->target_id]);
      }
    }
  }

}

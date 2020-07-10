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

    // If this item is already a community record, it cannot have a community record attached to it.
    if (mukurtu_community_records_is_community_record($entity)) {
      $this->context->addViolation($constraint->nestedCommunityRecord, ['%title' => $entity->title->value, '%id' => $entity->id()]);
    }

    foreach ($items as $item) {
      $target_id = $item->target_id;

      // Are we trying to set a circular reference? An item cannot be its own community record.
      if ($target_id == $entity_id) {
        $this->context->addViolation($constraint->circularReference, ['%title' => $entity->title->value, '%id' => $entity->id()]);
      }

      // Are we trying to attach a record that's already attached to another item?
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($target_id);
      $attached_to = mukurtu_community_records_is_community_record($node);
      if ($attached_to && $attached_to != $entity_id) {
        $account = \Drupal::currentUser();
        if ($node->access('view', $account)) {
          $this->context->addViolation($constraint->invalidTargetAccess, ['%title' => $node->title->value, '%id' => $target_id]);
        } else {
          // User doesn't have access to view the existing community record, don't leak the title.
          $this->context->addViolation($constraint->invalidTargetNoAccess, ['%id' => $target_id]);
        }
      }
    }
  }
}

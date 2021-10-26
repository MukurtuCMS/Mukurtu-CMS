<?php

namespace Drupal\mukurtu_community_records\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\Url;

/**
 * Validates the ValidOriginalRecord constraint.
 */
class ValidOriginalRecordValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Get the owning entity and its ID.
    $entity = $items->getEntity();
    $entity_id = $entity->id();

    if (mukurtu_community_records_is_original_record($entity) !== FALSE) {
      // This entity already has community records so it cannot
      // be a community record.
      $this->context->addViolation($constraint->nestedCommunityRecord, ['%title' => $entity->title->value, '%id' => $entity->id()]);
    }

    foreach ($items as $item) {
      $target_id = $item->target_id;

      // Are we trying to set a circular reference?
      // An item cannot be its own original record.
      if ($target_id === $entity_id) {
        $this->context->addViolation($constraint->circularReference, ['%title' => $entity->title->value, '%id' => $entity->id()]);
      }

      if ($target_id) {
        $entity_type = $item->getFieldDefinition()->getSetting('target_type');
        $target_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($target_id);

        // Target original record cannot be a community record.
        if (mukurtu_community_records_is_community_record($target_entity)) {
          $this->context->addViolation($constraint->invalidTargetNoAccess, ['%id' => $target_id]);
        }

        if ($entity->isNew()) {
          // If this is a brand new CR, we need to make sure the creator has
          // correct protocol access to the original record.
          // We do that by cheating and testing access to the CR creation route.
          $params = ['node' => $target_id, 'node_type' => $entity->bundle()];
          $url = Url::fromRoute('mukurtu_community_records.add_new_record_by_type', $params);

          if (!$url->access()) {
            $this->context->addViolation($constraint->invalidTargetNoAccess, ['%id' => $target_id]);
          }
        }
      }
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_community_records\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityInterface;
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
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof ValidOriginalRecord);

    // Get the owning entity and its ID.
    $entity = $value->getEntity();
    $entity_id = $entity->id();

    // If the field is empty, we have no opinion.
    if ($value->count() === 0) {
      return;
    }

    if (mukurtu_community_records_is_original_record($entity) !== FALSE) {
      // This entity already has community records so it cannot
      // be a community record.
      $this->context->addViolation($constraint->nestedCommunityRecord, ['%title' => $entity->title->value, '%id' => $entity->id()]);
    }

    foreach ($value as $item) {
      $target_id = $item->target_id;

      // Are we trying to set a circular reference?
      // An item cannot be its own original record.
      if ($target_id === $entity_id) {
        $this->context->addViolation($constraint->circularReference, ['%title' => $entity->title->value, '%id' => $entity->id()]);
      }

      $target_entity = $item->entity;
      if (!$target_entity instanceof EntityInterface) {
        continue;
      }

      // Target original record cannot be a community record.
      if (mukurtu_community_records_is_community_record($target_entity)) {
        $this->context->addViolation($constraint->invalidTargetNoAccess, ['%id' => $target_id]);
      }

      if (!$entity->isNew()) {
        continue;
      }

      // If this is a brand new CR, we need to make sure the creator has
      // correct protocol access to the original record. We do that by cheating
      // and testing access to the CR creation route.
      $params = ['node' => $target_id];
      $url = Url::fromRoute('mukurtu_community_records.add_new_record', $params);

      if (!$url->access()) {
        $this->context->addViolation($constraint->invalidTargetNoAccess, ['%id' => $target_id]);
      }
    }
  }

}

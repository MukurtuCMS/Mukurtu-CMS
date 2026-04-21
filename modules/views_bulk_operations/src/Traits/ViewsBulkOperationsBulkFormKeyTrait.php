<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Traits;

use Drupal\Core\Entity\EntityInterface;

/**
 * Bulk form key generation.
 */
trait ViewsBulkOperationsBulkFormKeyTrait {

  /**
   * Calculates the bulk form key for an entity.
   *
   * This generates a key that is used as the checkbox return value when
   * submitting the bulk form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to calculate a bulk form key for.
   * @param mixed $base_field_value
   *   The value of the base field for this view result.
   *
   * @return string
   *   The bulk form key representing the entity id, language and revision (if
   *   applicable) as one string.
   *
   * @see self::loadEntityFromBulkFormKey()
   */
  public static function calculateEntityBulkFormKey(EntityInterface $entity, $base_field_value): string {
    // We don't really need the entity ID or type ID, since only the
    // base field value and language are used to select rows, but
    // other modules may need those values.
    $key_parts = [
      $base_field_value,
      $entity->language()->getId(),
      $entity->getEntityTypeId(),
      $entity->id(),
    ];

    // An entity ID could be an arbitrary string (although they are typically
    // numeric). JSON then Base64 encoding ensures the bulk_form_key is
    // safe to use in HTML, and that the key parts can be retrieved.
    $key = \json_encode($key_parts);
    return \base64_encode($key);
  }

}

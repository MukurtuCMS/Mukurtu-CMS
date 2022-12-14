<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate_plus\Plugin\migrate\process\EntityLookup;

/**
 * This plugin looks for existing entities.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_entity_lookup"
 * )
 */
class MukurtuEntityLookup extends EntityLookup {

  /**
   * Determine if a value is a valid entity ID for the user to reference.
   *
   * @param mixed $value
   *   The value of the potential ID.
   *
   * @return boolean
   *   True if a valid ID, False otherwise.
   */
  protected function isValidIdentifier($value) {
    $id = is_array($value) ? reset($value) : $value;
    if (is_numeric($id)) {
      $entity = $this->entityTypeManager->getStorage($this->lookupEntityType)->load($id);
      if ($entity && (!$this->accessCheck || $entity->access('view'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks for the existence of some value.
   *
   * @param mixed $value
   *   The value to query.
   *
   * @return mixed|null
   *   Entity id if the queried entity exists. Otherwise NULL.
   */
  protected function query($value) {
    return $this->isValidIdentifier($value) ? $value : parent::query($value);
  }

}

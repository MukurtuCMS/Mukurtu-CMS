<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate_plus\Plugin\migrate\process\EntityLookup;

/**
 * This plugin looks for existing entities.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_entity_lookup",
 *   handle_multiples = TRUE
 * )
 */
class MukurtuEntityLookup extends EntityLookup {

  /**
   * Determine if a value is a valid entity ID for the user to reference.
   *
   * @param mixed $id
   *   The value of the potential ID.
   *
   * @return boolean
   *   True if a valid ID, False otherwise.
   */
  protected function isValidIdentifier($id) {
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
    $multiple = is_array($value);
    $values = $multiple ? $value : [$value];

    $results = [];
    foreach ($values as $v) {
      // Try as an ID of the destination type, otherwise try an entity lookup.
      $results[] = $this->isValidIdentifier($v) ? $v : parent::query($v);
    }
    return empty($results) ? NULL : ($multiple ? $results : reset($results));
  }

}

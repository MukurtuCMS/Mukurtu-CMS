<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\migrate\MigrateException;
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
   * {@inheritdoc}
   */
  protected function query($value) {
    if ($this->isValidIdentifier($value)) {
      return $value;
    }

    // Below is basically a copy of migrate_plus EntityLookup::query but with
    // some tweaks to always respect access and to throw an exception when
    // multiple query results are found.

    // Entity queries typically are case-insensitive. Therefore, we need to
    // handle case-sensitive filtering as a post-query step. By default, it
    // filters case-insensitive. Change to true if that is not the desired
    // outcome.
    $ignore_case = !empty($this->configuration['ignore_case']) ?: FALSE;
    $operator = !empty($this->configuration['operator']) ? $this->configuration['operator'] : '=';
    $multiple = is_array($value);

    // Apply correct operator for multiple values.
    if ($multiple && $operator === '=') {
      $operator = 'IN';
    }

    $query = $this->entityTypeManager->getStorage($this->lookupEntityType)
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition($this->lookupValueKey, $value, $operator);
    // Sqlite and possibly others returns data in a non-deterministic order.
    // Make it deterministic.
    if ($multiple) {
      $query->sort($this->lookupValueKey, 'DESC');
    }

    if ($this->lookupBundleKey) {
      $query->condition($this->lookupBundleKey, (array) $this->lookupBundle, 'IN');
    }
    $results = $query->execute();

    // Don't allow ambiguous queries. Query must resolve to one or zero items.
    if (count($results) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple items share the same label. Try referencing the item\'s ID.', $value));
    }

    if (empty($results)) {
      return NULL;
    }

    // Do a case-sensitive comparison only for strict operators.
    if (!$ignore_case && in_array($operator, ['=', 'IN'], TRUE)) {
      // Returns the entity's identifier.
      foreach ($results as $k => $identifier) {
        $entity = $this->entityTypeManager->getStorage($this->lookupEntityType)->load($identifier);
        $result_value = $entity instanceof ConfigEntityInterface ? $entity->get($this->lookupValueKey) : $entity->get($this->lookupValueKey)->value;
        if (($multiple && !in_array($result_value, $value, TRUE)) || (!$multiple && $result_value !== $value)) {
          unset($results[$k]);
        }
      }
    }

    if ($multiple && !empty($this->destinationProperty)) {
      array_walk($results, function (&$value): void {
        $value = [$this->destinationProperty => $value];
      });
    }

    return $multiple ? array_values($results) : reset($results);
  }

}

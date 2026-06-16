<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityInterface;

/**
 * Resolves child entities for aggregative content types.
 *
 * Returns the set of child entity IDs that should be bundled alongside a
 * parent when "Export with contents" is used. Use getChildEntities() for
 * one level deep, or getChildEntitiesRecursive() to traverse all nested
 * collection levels.
 */
class ExportChildResolver {

  /**
   * Returns child entity IDs for the given entity, keyed by entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity to resolve children for.
   *
   * @return array
   *   Array in the form {entity_type => [id => id, ...]}.
   *   Empty array if the entity has no resolvable children.
   */
  public function getChildEntities(EntityInterface $entity): array {
    if ($entity->getEntityTypeId() !== 'node') {
      return [];
    }

    $children = [];

    switch ($entity->bundle()) {
      case 'collection':
        foreach (['field_items_in_collection', 'field_child_collections'] as $field_name) {
          if ($entity->hasField($field_name)) {
            foreach ($entity->get($field_name)->referencedEntities() as $child) {
              $id = (int) $child->id();
              $children['node'][$id] = $id;
            }
          }
        }
        break;

      case 'word_list':
        if ($entity->hasField('field_words')) {
          foreach ($entity->get('field_words')->referencedEntities() as $child) {
            $id = (int) $child->id();
            $children['node'][$id] = $id;
          }
        }
        break;
    }

    return $children;
  }

  /**
   * Returns child entity IDs for all nested collection levels.
   *
   * Traverses field_child_collections recursively and collects
   * field_items_in_collection from every level. Cycle detection prevents
   * infinite loops from circular collection references.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent collection entity to resolve children for.
   *
   * @return array
   *   Array in the form {entity_type => [id => id, ...]}.
   */
  public function getChildEntitiesRecursive(EntityInterface $entity): array {
    $visited = [];
    return $this->collectChildEntitiesRecursive($entity, $visited);
  }

  /**
   * Internal recursive helper for getChildEntitiesRecursive().
   */
  private function collectChildEntitiesRecursive(EntityInterface $entity, array &$visited): array {
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'collection') {
      return [];
    }

    $entity_id = (int) $entity->id();
    if (isset($visited[$entity_id])) {
      return [];
    }
    $visited[$entity_id] = TRUE;

    $children = [];
    foreach (['field_items_in_collection', 'field_child_collections'] as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      foreach ($entity->get($field_name)->referencedEntities() as $child) {
        $id = (int) $child->id();
        $children['node'][$id] = $id;
        if ($field_name === 'field_child_collections') {
          foreach ($this->collectChildEntitiesRecursive($child, $visited) as $type => $ids) {
            $children[$type] = ($children[$type] ?? []) + $ids;
          }
        }
      }
    }

    return $children;
  }

}

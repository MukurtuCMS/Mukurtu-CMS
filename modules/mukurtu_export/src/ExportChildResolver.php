<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityInterface;

/**
 * Resolves direct child entities for aggregative content types.
 *
 * Returns the set of child entity IDs that should be bundled alongside a
 * parent when "Export with contents" is used. Only traverses one level deep.
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

}

<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_multipage_items\MultipageItemInterface;
use Drupal\mukurtu_multipage_items\MultipageItemManager;
use Drupal\node\NodeInterface;

/**
 * Resolves child entities for aggregative content types.
 *
 * Returns the set of child entity IDs that should be bundled alongside a
 * parent when "Export with contents" is used. Use getChildEntities() for
 * one level deep, or getChildEntitiesRecursive() to traverse all nested
 * collection levels.
 */
class ExportChildResolver {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MultipageItemManager $multipageItemManager,
  ) {}

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
   * Returns community records accessible to the current user for an original record.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The candidate original record.
   *
   * @return \Drupal\node\NodeInterface[]
   *   CR nodes keyed by nid, sorted newest first. Empty if none found or if
   *   the node is itself a community record.
   */
  public function getAccessibleCommunityRecords(NodeInterface $node): array {
    if (!$node->hasField('field_mukurtu_original_record')) {
      return [];
    }
    // If the field is populated this node is itself a CR, not an OR.
    if (!$node->get('field_mukurtu_original_record')->isEmpty()) {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('field_mukurtu_original_record', $node->id())
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->execute();
    if (empty($ids)) {
      return [];
    }
    return $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
  }

  /**
   * Returns the original record if this node is a community record.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The candidate community record.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The original record node, or NULL if this is not a community record.
   */
  public function getOriginalRecord(NodeInterface $node): ?NodeInterface {
    if (!$node->hasField('field_mukurtu_original_record')) {
      return NULL;
    }
    if ($node->get('field_mukurtu_original_record')->isEmpty()) {
      return NULL;
    }
    return $node->get('field_mukurtu_original_record')->entity;
  }

  /**
   * Returns the multipage_item entity this node belongs to, or NULL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Any page in the multipage item.
   *
   * @return \Drupal\mukurtu_multipage_items\MultipageItemInterface|null
   *   The multipage_item entity, or NULL if the node is not part of one.
   */
  public function getMultipageEntity(NodeInterface $node): ?MultipageItemInterface {
    return $this->multipageItemManager->getMultipageEntity($node);
  }

  /**
   * Returns accessible pages for the multipage item this node belongs to.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Any page in the multipage item.
   *
   * @return \Drupal\node\NodeInterface[]
   *   All accessible page nodes keyed by nid, in field_pages order.
   *   Empty if the node is not part of a multipage item.
   */
  public function getMultipagePages(NodeInterface $node): array {
    $mpi = $this->multipageItemManager->getMultipageEntity($node);
    if (!$mpi) {
      return [];
    }
    return $mpi->getPages(TRUE);
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

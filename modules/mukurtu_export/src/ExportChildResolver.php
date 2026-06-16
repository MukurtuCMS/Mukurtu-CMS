<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_multipage_items\MultipageItemManager;
use Drupal\node\NodeInterface;

/**
 * Resolves direct child entities for aggregative content types.
 *
 * Returns the set of child entity IDs that should be bundled alongside a
 * parent when "Export with contents" is used. Only traverses one level deep.
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

}

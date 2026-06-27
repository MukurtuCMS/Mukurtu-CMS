<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\MenuRebuildProcessor;

/**
 * Hook implementations for collection entities.
 */
final class CollectionEntityHooks {

  /**
   * Constructs a new CollectionEntityHooks.
   *
   * @param \Drupal\mukurtu_collection\MenuRebuildProcessor $menuRebuildProcessor
   *   Menu rebuild processor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected MenuRebuildProcessor $menuRebuildProcessor,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * React to collection node insert.
   */
  #[Hook('node_insert')]
  public function entityTypeInsert(EntityInterface $entity): void {
    // Only process collection nodes.
    if (!$entity instanceof Collection) {
      return;
    }

    // Always rebuild menu when a collection is created.
    $this->menuRebuildProcessor->markRebuildNeeded();

    // Reindex any sub-collections added at creation time so their
    // parent_collection_id search index field is updated immediately.
    $child_ids = $entity->getChildCollectionIds();
    if (!empty($child_ids)) {
      $this->reindexCollections($child_ids);
    }
  }

  /**
   * React to collection node update.
   */
  #[Hook('node_update')]
  public function entityTypeUpdate(EntityInterface $entity): void {
    // Only process collection nodes.
    if (!$entity instanceof Collection) {
      return;
    }

    // Check if there's an original to compare against.
    if (!isset($entity->original) || !$entity->original instanceof Collection) {
      // No original, rebuild to be safe.
      $this->menuRebuildProcessor->markRebuildNeeded();
      return;
    }

    $original = $entity->original;

    // Check if field_child_collections has changed.
    $new_child_ids = $entity->getChildCollectionIds();
    $old_child_ids = $original->getChildCollectionIds();

    // If the child collections have changed, rebuild the menu and reindex
    // affected children so their parent_collection_id search index field
    // reflects the new parent state immediately.
    if ($new_child_ids !== $old_child_ids) {
      $this->menuRebuildProcessor->markRebuildNeeded();

      $added = array_diff($new_child_ids, $old_child_ids);
      $removed = array_diff($old_child_ids, $new_child_ids);
      $affected = array_unique(array_merge($added, $removed));

      if (!empty($affected)) {
        $this->reindexCollections($affected);
      }

      return;
    }

    // Check if the collection's title changed (affects menu link titles).
    if ($entity->label() !== $original->label()) {
      $this->menuRebuildProcessor->markRebuildNeeded();
      return;
    }
  }

  /**
   * Marks collection nodes for immediate Search API reindexing.
   *
   * @param int[] $node_ids
   *   Node IDs to reindex.
   */
  private function reindexCollections(array $node_ids): void {
    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager
      ->getStorage('search_api_index')
      ->load('mukurtu_collection_index');
    if (!$index) {
      return;
    }
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
    $item_ids = [];
    foreach ($nodes as $node) {
      $item_ids[] = $node->id() . ':' . $node->language()->getId();
    }
    if (!empty($item_ids)) {
      $index->trackItemsUpdated('entity:node', $item_ids);
    }
  }

  /**
   * React to collection node delete.
   */
  #[Hook('node_delete')]
  public function entityTypeDelete(EntityInterface $entity): void {
    // Only process collection nodes.
    if (!$entity instanceof Collection) {
      return;
    }

    // Always rebuild menu when a collection is deleted.
    $this->menuRebuildProcessor->markRebuildNeeded();
  }

}

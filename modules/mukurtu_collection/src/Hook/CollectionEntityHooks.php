<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Core\Entity\EntityInterface;
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
   */
  public function __construct(protected MenuRebuildProcessor $menuRebuildProcessor) {
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

    // If the child collections have changed, rebuild the menu.
    if ($new_child_ids !== $old_child_ids) {
      $this->menuRebuildProcessor->markRebuildNeeded();
      return;
    }

    // Check if the collection's title changed (affects menu link titles).
    if ($entity->label() !== $original->label()) {
      $this->menuRebuildProcessor->markRebuildNeeded();
      return;
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

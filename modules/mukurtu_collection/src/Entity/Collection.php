<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;


class Collection extends Node implements CollectionInterface {
  /**
   * {@inheritdoc}
   */
  public function add(EntityInterface $entity): void {
    $items = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
    $items[] = ['target_id' => $entity->id()];
    $this->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(EntityInterface $entity): void {
    $needle = $entity->id();
    $items = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
    foreach ($items as $delta => $item) {
      if ($item['target_id'] == $needle) {
        unset($items[$delta]);
      }
    }
    $this->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(): int {
    $childCollections = $this->getChildCollections();

    // Count the items in the children.
    $childCollectionsCount = 0;
    if (!empty($childCollections)) {
      /**
       * @var \Drupal\mukurtu_collection\Entity\Collection $childCollection
       */
      foreach ($childCollections as $childCollection) {
        // Don't count things the user can't see.
        if ($childCollection->access('view')) {
          $childCollectionsCount += $childCollection->getCount();
        }
      }
    }

    // Count the items in this single collection.
    $items = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
    if (is_countable($items)) {
      return count($items) + $childCollectionsCount;
    }
    return 0 + $childCollectionsCount;
  }

  /**
   * Get immediate (depth = 1) child collections.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface[]
   */
  public function getChildCollections() {
    $collections = $this->get('field_child_collections')->referencedEntities() ?? NULL;
    return $collections;
  }

  /**
   * Add a collection as a sub-collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The parent collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   *   The updated collection entity.
   */
  private function setParentCollection(CollectionInterface $collection): Collection {
    $this->set('field_parent_collection', ['target_id' => $collection->id()]);
    return $this;
  }

  /**
   * Add a collection as a sub-collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The child collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   *   The parent of the newly added child collection.
   */
  public function addChildCollection(Collection $collection): Collection {
    // Set the child relationship.
    $childCollectionsRefs = $this->get('field_child_collections')->getValue();
    $childCollectionsRefs[] = ['target_id' => $collection->id()];
    $this->set('field_child_collections', $childCollectionsRefs);

    // Set the parent collection relationship.
    $collection->setParentCollection($this);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Invalid the cache of referenced entities
    // to trigger recalculation of the computed fields.
    $refs = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->referencedEntities() ?? NULL;
    if (!empty($refs)) {
      foreach ($refs as $ref) {
        Cache::invalidateTags($ref->getCacheTagsToInvalidate());
      }
    }

    // Invalid the collection's cache as well.
    Cache::invalidateTags($this->getCacheTagsToInvalidate());
  }

}

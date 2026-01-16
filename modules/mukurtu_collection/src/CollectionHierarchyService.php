<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing collection hierarchies.
 */
class CollectionHierarchyService implements CollectionHierarchyServiceInterface {

  /**
   * Cache of processed collections to prevent circular references.
   *
   * @var array
   */
  protected array $processedCollections = [];

  /**
   * Constructs a new CollectionHierarchyService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected AccountProxyInterface $currentUser) {
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCollections(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    // Get all collection IDs that are referenced as child collections.
    // @todo There should be a better way to find all root ids in one query,
    //   might require using the DB service instead of entity query. Need to go
    //   to DB query b/c you want to join node, or node_field_data, to
    //   node__field_child_collections.field_child_collections_target_id.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->accessCheck(FALSE)
      ->exists('field_child_collections');
    $parents_with_children = $query->execute();

    $child_collection_ids = [];
    if (!empty($parents_with_children)) {
      $parent_collections = $storage->loadMultiple($parents_with_children);
      foreach ($parent_collections as $parent) {
        if ($parent instanceof Collection) {
          $child_collection_ids = array_merge($child_collection_ids, $parent->getChildCollectionIds());
        }
      }
    }

    // Get all collections that are NOT in the child collections list.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->accessCheck(FALSE);

    if (!empty($child_collection_ids)) {
      $query->condition('nid', $child_collection_ids, 'NOT IN');
    }

    $root_ids = $query->execute();

    return $storage->loadMultiple($root_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionHierarchy(int $root_collection_id, ?int $max_depth = NULL): array {
    // Reset processed collections cache for this operation.
    $this->processedCollections = [];

    $storage = $this->entityTypeManager->getStorage('node');
    $root_collection = $storage->load($root_collection_id);

    if (!$root_collection instanceof Collection) {
      return [];
    }

    // Drupal menu system max depth is 9.
    if ($max_depth === NULL) {
      $max_depth = 9;
    }

    return $this->buildHierarchyRecursive($root_collection, 0, $max_depth);
  }

  /**
   * Recursively build the hierarchy structure.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The collection to process.
   * @param int $current_depth
   *   The current depth in the hierarchy.
   * @param int $max_depth
   *   Maximum depth to traverse.
   *
   * @return array
   *   Hierarchy structure.
   */
  protected function buildHierarchyRecursive(Collection $collection, int $current_depth, int $max_depth): array {
    $collection_id = $collection->id();

    // Prevent circular references and infinite loops.
    if (isset($this->processedCollections[$collection_id])) {
      return [];
    }

    $this->processedCollections[$collection_id] = TRUE;

    $structure = [
      'entity' => $collection,
      'depth' => $current_depth,
      'children' => [],
    ];

    // Stop if we've reached max depth.
    if ($current_depth >= $max_depth) {
      return $structure;
    }

    // Get child collections.
    $child_collections = $collection->getChildCollections();

    if ($child_collections) {
      foreach ($child_collections as $child_collection) {
        // Check access and published status.
        if ($child_collection instanceof Collection &&
          $child_collection->access() &&
          $child_collection->isPublished()) {
          $child_structure = $this->buildHierarchyRecursive($child_collection, $current_depth + 1, $max_depth);
          if (!empty($child_structure)) {
            $structure['children'][] = $child_structure;
          }
        }
      }
    }

    return $structure;
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCollectionForCollection(Collection $collection): ?Collection {
    // If this is already a root collection, return it.
    if ($this->isRootCollection($collection)) {
      return $collection;
    }

    // Traverse up the hierarchy to find the root.
    $visited = [];
    $current = $collection;

    while ($current) {
      $current_id = $current->id();

      // Prevent infinite loops.
      if (isset($visited[$current_id])) {
        break;
      }
      $visited[$current_id] = TRUE;

      // Get parent collection.
      $parent = $current->getParentCollection();

      if (!$parent) {
        // No parent means this is the root.
        return $current;
      }

      $current = $parent;
    }

    return $current;
  }

  /**
   * {@inheritdoc}
   */
  public function isRootCollection(Collection $collection): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    // Check if any collection references this collection in field_child_collections.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->condition('field_child_collections', $collection->id())
      ->accessCheck(FALSE)
      ->range(0, 1);

    $results = $query->execute();

    // If no results, this collection is not referenced by any parent.
    return empty($results);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionFromNode(NodeInterface $node): ?Collection {
    if ($node instanceof Collection) {
      return $node;
    }
    return NULL;
  }

}

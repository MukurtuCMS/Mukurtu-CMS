<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
    $parentsWithChildren = $query->execute();

    $childCollectionIds = [];
    if (!empty($parentsWithChildren)) {
      $parentCollections = $storage->loadMultiple($parentsWithChildren);
      foreach ($parentCollections as $parent) {
        if ($parent instanceof CollectionInterface) {
          $childCollectionIds = array_merge($childCollectionIds, $parent->getChildCollectionIds());
        }
      }
    }

    // Get all collections that are NOT in the child collections list.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->accessCheck(FALSE);

    if (!empty($childCollectionIds)) {
      $query->condition('nid', $childCollectionIds, 'NOT IN');
    }

    $rootIds = $query->execute();

    return $storage->loadMultiple($rootIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionHierarchy(int $root_collection_id, ?int $max_depth = NULL): array {
    // Reset processed collections cache for this operation.
    $this->processedCollections = [];

    $storage = $this->entityTypeManager->getStorage('node');
    $root_collection = $storage->load($root_collection_id);

    if (!$root_collection || !$root_collection instanceof CollectionInterface) {
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
   * @param \Drupal\mukurtu_collection\Entity\CollectionInterface $collection
   *   The collection to process.
   * @param int $current_depth
   *   The current depth in the hierarchy.
   * @param int $max_depth
   *   Maximum depth to traverse.
   *
   * @return array
   *   Hierarchy structure.
   */
  protected function buildHierarchyRecursive(CollectionInterface $collection, int $current_depth, $max_depth) {
    $collectionId = $collection->id();

    // Prevent circular references and infinite loops.
    if (isset($this->processedCollections[$collectionId])) {
      return [];
    }

    $this->processedCollections[$collectionId] = TRUE;

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
        if ($child_collection instanceof CollectionInterface &&
          $child_collection->access('view') &&
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
  public function getRootCollectionForCollection(int $collection_id): ?CollectionInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $collection = $storage->load($collection_id);

    if (!$collection || !$collection instanceof CollectionInterface) {
      return NULL;
    }

    // If this is already a root collection, return it.
    if ($this->isRootCollection($collection_id)) {
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
  public function isRootCollection(int $collection_id): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    // Check if any collection references this collection in field_child_collections.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->condition('field_child_collections', $collection_id)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $results = $query->execute();

    // If no results, this collection is not referenced by any parent.
    return empty($results);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildCollections(int $collection_id, int $depth = 1): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $collection = $storage->load($collection_id);

    if (!$collection || !$collection instanceof CollectionInterface) {
      return [];
    }

    if ($depth === 1) {
      // Just return immediate children.
      return $collection->getChildCollections() ?? [];
    }

    // For deeper depths, we need to traverse recursively.
    $this->processedCollections = [];
    return $this->getChildCollectionsRecursive($collection, $depth, 0);
  }

  /**
   * Recursively get child collections.
   *
   * @param \Drupal\mukurtu_collection\Entity\CollectionInterface $collection
   *   The collection to process.
   * @param int $target_depth
   *   The target depth to traverse.
   * @param int $current_depth
   *   The current depth.
   *
   * @return array
   *   Array of child collections.
   */
  protected function getChildCollectionsRecursive(CollectionInterface $collection, int $target_depth, int $current_depth) {
    $collection_id = $collection->id();

    // Prevent circular references.
    if (isset($this->processedCollections[$collection_id])) {
      return [];
    }

    $this->processedCollections[$collection_id] = TRUE;

    $children = [];
    $immediate_children = $collection->getChildCollections() ?? [];

    foreach ($immediate_children as $child) {
      if ($child instanceof CollectionInterface &&
          $child->access('view') &&
          $child->isPublished()) {
        $children[] = $child;

        // Continue recursing if we haven't reached target depth.
        if ($current_depth + 1 < $target_depth) {
          $grandchildren = $this->getChildCollectionsRecursive($child, $target_depth, $current_depth + 1);
          $children = array_merge($children, $grandchildren);
        }
      }
    }

    return $children;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionFromNode(NodeInterface $node): ?CollectionInterface {
    if ($node->bundle() === 'collection' && $node instanceof CollectionInterface) {
      return $node;
    }
    return NULL;
  }

}

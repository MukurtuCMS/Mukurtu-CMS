<?php

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Cache of processed collections to prevent circular references.
   *
   * @var array
   */
  protected $processedCollections = [];

  /**
   * Constructs a new CollectionHierarchyService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCollections() {
    $storage = $this->entityTypeManager->getStorage('node');

    // Get all collection IDs that are referenced as child collections.
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
  public function getCollectionHierarchy($rootCollectionId, $maxDepth = NULL) {
    // Reset processed collections cache for this operation.
    $this->processedCollections = [];

    $storage = $this->entityTypeManager->getStorage('node');
    $rootCollection = $storage->load($rootCollectionId);

    if (!$rootCollection || !$rootCollection instanceof CollectionInterface) {
      return [];
    }

    // Drupal menu system max depth is 9.
    if ($maxDepth === NULL) {
      $maxDepth = 9;
    }

    return $this->buildHierarchyRecursive($rootCollection, 0, $maxDepth);
  }

  /**
   * Recursively build the hierarchy structure.
   *
   * @param \Drupal\mukurtu_collection\Entity\CollectionInterface $collection
   *   The collection to process.
   * @param int $currentDepth
   *   The current depth in the hierarchy.
   * @param int $maxDepth
   *   Maximum depth to traverse.
   *
   * @return array
   *   Hierarchy structure.
   */
  protected function buildHierarchyRecursive(CollectionInterface $collection, $currentDepth, $maxDepth) {
    $collectionId = $collection->id();

    // Prevent circular references and infinite loops.
    if (isset($this->processedCollections[$collectionId])) {
      return [];
    }

    $this->processedCollections[$collectionId] = TRUE;

    $structure = [
      'entity' => $collection,
      'depth' => $currentDepth,
      'children' => [],
    ];

    // Stop if we've reached max depth.
    if ($currentDepth >= $maxDepth) {
      return $structure;
    }

    // Get child collections.
    $childCollections = $collection->getChildCollections();

    if ($childCollections) {
      foreach ($childCollections as $childCollection) {
        // Check access and published status.
        if ($childCollection instanceof CollectionInterface &&
            $childCollection->access('view') &&
            $childCollection->isPublished()) {
          $childStructure = $this->buildHierarchyRecursive($childCollection, $currentDepth + 1, $maxDepth);
          if (!empty($childStructure)) {
            $structure['children'][] = $childStructure;
          }
        }
      }
    }

    return $structure;
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCollectionForCollection($collectionId) {
    $storage = $this->entityTypeManager->getStorage('node');
    $collection = $storage->load($collectionId);

    if (!$collection || !$collection instanceof CollectionInterface) {
      return NULL;
    }

    // If this is already a root collection, return it.
    if ($this->isRootCollection($collectionId)) {
      return $collection;
    }

    // Traverse up the hierarchy to find the root.
    $visited = [];
    $current = $collection;

    while ($current) {
      $currentId = $current->id();

      // Prevent infinite loops.
      if (isset($visited[$currentId])) {
        break;
      }
      $visited[$currentId] = TRUE;

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
  public function isRootCollection($collectionId) {
    $storage = $this->entityTypeManager->getStorage('node');

    // Check if any collection references this collection in field_child_collections.
    $query = $storage->getQuery()
      ->condition('type', 'collection')
      ->condition('field_child_collections', $collectionId)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $results = $query->execute();

    // If no results, this collection is not referenced by any parent.
    return empty($results);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildCollections($collectionId, $depth = 1) {
    $storage = $this->entityTypeManager->getStorage('node');
    $collection = $storage->load($collectionId);

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
   * @param int $targetDepth
   *   The target depth to traverse.
   * @param int $currentDepth
   *   The current depth.
   *
   * @return array
   *   Array of child collections.
   */
  protected function getChildCollectionsRecursive(CollectionInterface $collection, $targetDepth, $currentDepth) {
    $collectionId = $collection->id();

    // Prevent circular references.
    if (isset($this->processedCollections[$collectionId])) {
      return [];
    }

    $this->processedCollections[$collectionId] = TRUE;

    $children = [];
    $immediateChildren = $collection->getChildCollections() ?? [];

    foreach ($immediateChildren as $child) {
      if ($child instanceof CollectionInterface &&
          $child->access('view') &&
          $child->isPublished()) {
        $children[] = $child;

        // Continue recursing if we haven't reached target depth.
        if ($currentDepth + 1 < $targetDepth) {
          $grandchildren = $this->getChildCollectionsRecursive($child, $targetDepth, $currentDepth + 1);
          $children = array_merge($children, $grandchildren);
        }
      }
    }

    return $children;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionFromNode(NodeInterface $node) {
    if ($node->bundle() === 'collection' && $node instanceof CollectionInterface) {
      return $node;
    }
    return NULL;
  }

}

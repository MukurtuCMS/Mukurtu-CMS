<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for the collection hierarchy service.
 */
interface CollectionHierarchyServiceInterface {

  /**
   * Get all root collections.
   *
   * Root collections are collections that are not referenced in any other
   * collection's field_child_collections field.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface[]
   *   Array of root collection entities.
   */
  public function getRootCollections(): array;

  /**
   * Get the full hierarchy for a given root collection.
   *
   * @param int $root_collection_id
   *   The node ID of the root collection.
   * @param int|null $max_depth
   *   Maximum depth to traverse. NULL for unlimited (up to menu system max).
   *
   * @return array
   *   Nested array representing the collection hierarchy with structure:
   *   [
   *     'entity' => CollectionInterface,
   *     'children' => [ ... recursive structure ... ],
   *     'depth' => int,
   *   ]
   */
  public function getCollectionHierarchy(int $root_collection_id, ?int $max_depth = NULL): array;

  /**
   * Find the root collection for a given collection.
   *
   * @param int $collection_id
   *   The node ID of the collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface|null
   *   The root collection entity, or NULL if not found.
   */
  public function getRootCollectionForCollection(int $collection_id): ?CollectionInterface;

  /**
   * Check if a collection is a root collection.
   *
   * @param int $collection_id
   *   The node ID of the collection.
   *
   * @return bool
   *   TRUE if the collection is a root collection, FALSE otherwise.
   */
  public function isRootCollection(int $collection_id): bool;

  /**
   * Get child collections for a given collection.
   *
   * @todo this isn't being used..
   *
   * @param int $collection_id
   *   The node ID of the parent collection.
   * @param int $depth
   *   Depth to traverse. 1 = immediate children only.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface[]
   *   Array of child collection entities.
   */
  public function getChildCollections(int $collection_id, int $depth = 1): array;

  /**
   * Determine if a node is a collection or part of a collection hierarchy.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface|null
   *   The collection entity if the node is a collection, NULL otherwise.
   */
  public function getCollectionFromNode(NodeInterface $node): ?CollectionInterface;

}

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\mukurtu_collection\Entity\Collection;
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
   * @return \Drupal\mukurtu_collection\Entity\Collection[]
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
   *     'entity' => Collection,
   *     'children' => [ ... recursive structure ... ],
   *     'depth' => int,
   *   ]
   */
  public function getCollectionHierarchy(int $root_collection_id, ?int $max_depth = NULL): array;

  /**
   * Find the root collection for a given collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection|null
   *   The root collection entity, or NULL if not found.
   */
  public function getRootCollectionForCollection(Collection $collection): ?Collection;

  /**
   * Check if a collection is a root collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The collection.
   *
   * @return bool
   *   TRUE if the collection is a root collection, FALSE otherwise.
   */
  public function isRootCollection(Collection $collection): bool;

  /**
   * Determine if a node is a collection or part of a collection hierarchy.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection|null
   *   The collection entity if the node is a collection, NULL otherwise.
   */
  public function getCollectionFromNode(NodeInterface $node): ?Collection;

}

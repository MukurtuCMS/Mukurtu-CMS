<?php

namespace Drupal\tagify\Services;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for managing hierarchical taxonomy terms.
 */
interface TagifyHierarchicalTermManagerInterface {

  /**
   * Get a cached term lookup table spanning the whole tree.
   *
   * @param string $bundle
   *   The name of the bundle.
   *
   * @return array
   *   The taxonomy term whole tree.
   */
  public function getTree(string $bundle): array;

  /**
   * Get first parents.
   *
   * @param array $tree
   *   The taxonomy term whole tree.
   * @param string $entity_id
   *   The ID of the entity.
   *
   * @return array
   *   The parents from a taxonomy term.
   */
  public function getParents(array $tree, string $entity_id): array;

  /**
   * Get parent name.
   *
   * @param string $entity_id
   *   The ID of the entity.
   * @param string $bundle
   *   The name of the bundle.
   *
   * @return string
   *   The parent name.
   */
  public function getParentName(string $entity_id, string $bundle): string;

  /**
   * Check if the entity is hierarchical.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is hierarchical, FALSE otherwise.
   */
  public function isHierarchical(?EntityInterface $entity): bool;

}

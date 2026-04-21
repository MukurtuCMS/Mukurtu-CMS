<?php

namespace Drupal\tagify\Services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service class for managing hierarchical taxonomy terms in Tagify.
 */
class TagifyHierarchicalTermManager implements TagifyHierarchicalTermManagerInterface {

  /**
   * The tree cache.
   *
   * @var array
   */
  protected array $treeCache = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a TagifyHierarchicalTermManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTree(string $bundle): array {
    if (empty($this->treeCache[$bundle])) {
      $tree = [];
      /** @var \Drupal\taxonomy\TermStorage $storage */
      $storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $flat = $storage->loadTree($bundle);
      foreach ($flat as $item) {
        $item->is_leaf = TRUE;
        $tree[$item->tid] = $item;
      }
      $this->treeCache[$bundle] = $tree;
    }
    return $this->treeCache[$bundle];
  }

  /**
   * {@inheritdoc}
   */
  public function getParents(array $tree, string $entity_id): array {
    $item = $tree[$entity_id] ?? NULL;
    if (!$item) {
      return [];
    }

    $result = [$item];
    $done = FALSE;

    while (!$done) {
      $parent = $item->parents[0] ?? FALSE;
      if ($parent && $parent !== $entity_id) {
        $result[] = $tree[$parent];
        $entity_id = $parent;
      }
      else {
        $done = TRUE;
      }
    }

    return array_reverse($result);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentName(string $entity_id, string $bundle): string {
    $tree = $this->getTree($bundle);
    $parents = $this->getParents($tree, $entity_id);
    if (count($parents) < 2) {
      return '';
    }

    $parent = $parents[0];
    return $parent ? $parent->name : '';
  }

  /**
   * {@inheritdoc}
   */
  public function isHierarchical(?EntityInterface $entity): bool {
    /** @var \Drupal\taxonomy\Entity\Term $entity */
    return $entity && !$entity->get('parent')->isEmpty();
  }

}

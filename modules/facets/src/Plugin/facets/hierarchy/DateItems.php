<?php

namespace Drupal\facets\Plugin\facets\hierarchy;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\facets\Hierarchy\HierarchyPluginBase;

/**
 * Provides the DateItems hierarchy class.
 *
 * @FacetsHierarchy(
 *   id = "date_items",
 *   label = @Translation("Date item hierarchy"),
 *   description = @Translation("Display hierarchical dates."),
 * )
 */
class DateItems extends HierarchyPluginBase {

  use UnchangingCacheableDependencyTrait;

  /**
   * Static cache for the parents.
   *
   * @var array
   */
  protected $parents = [];

  /**
   * Static cache for the children.
   *
   * @var array
   */
  protected $children = [];

  /**
   * {@inheritdoc}
   */
  public function getParentIds($id) {
    if (preg_match('/(.*)[-T:]\d+$/', $id, $matches)) {
      $this->parents[$id] = $matches[1];
      $this->children[$matches[1]][] = $id;
      return [$matches[1]];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getNestedChildIds($id) {
    $nested_children = [];
    if (isset($this->children[$id])) {
      foreach ($this->children[$id] as $child) {
        $nested_children[] = $child;
        $nested_children = array_merge($nested_children, $this->getNestedChildIds($child));
      }
    }

    return $nested_children;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildIds(array $ids) {
    foreach ($ids as $id) {
      $this->getParentIds($id);
    }
    return array_intersect_key($this->children, array_flip($ids));
  }

}

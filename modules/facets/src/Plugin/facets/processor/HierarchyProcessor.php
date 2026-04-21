<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * The hierarchy processor transforms the result in a tree.
 *
 * @FacetsProcessor(
 *   id = "hierarchy_processor",
 *   label = @Translation("Build hierarchy tree"),
 *   description = @Translation("Build the tree for facets that use hierarchy.."),
 *   stages = {
 *     "build" = 100,
 *   },
 *   locked = true
 * )
 */
class HierarchyProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * An array of all entity ids in the active resultset which are a child.
   *
   * @var string[]
   */
  protected $childIds = [];

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Handle hierarchy.
    if ($results && $facet->getUseHierarchy()) {
      $keyed_results = [];
      foreach ($results as $result) {
        $keyed_results[$result->getRawValue()] = $result;
      }
      $hierarchy = $facet->getHierarchyInstance();
      $facet->addCacheableDependency($hierarchy);

      $parent_groups = $hierarchy->getChildIds(array_keys($keyed_results));
      $keyed_results = $this->buildHierarchicalTree($keyed_results, $parent_groups);

      // Remove children from primary level.
      foreach (array_unique($this->childIds) as $child_id) {
        unset($keyed_results[$child_id]);
      }

      $results = array_values($keyed_results);
    }

    return $results;
  }

  /**
   * Builds a hierarchical structure for results.
   *
   * When given an array of results and an array which defines the hierarchical
   * structure, this will build the results structure and set all childs.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $keyed_results
   *   An array of results keyed by id.
   * @param array $parent_groups
   *   An array of 'child id arrays' keyed by their parent id.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   An array of results structured hierarchically.
   */
  protected function buildHierarchicalTree(array $keyed_results, array $parent_groups): array {
    foreach ($keyed_results as &$result) {
      $current_id = $result->getRawValue();
      if (isset($parent_groups[$current_id]) && $parent_groups[$current_id]) {
        $child_ids = $parent_groups[$current_id];
        $child_keyed_results = [];
        foreach ($child_ids as $child_id) {
          if (isset($keyed_results[$child_id])) {
            $child_keyed_results[$child_id] = $keyed_results[$child_id];
          }
          else {
            // Children could already be built by Facets Summary manager, if
            // they are, just loading them will suffice.
            $children = $keyed_results[$current_id]->getChildren();
            if (!empty($children[$child_id])) {
              $child_keyed_results[$child_id] = $children[$child_id];
            }
          }
        }
        $result->setChildren($child_keyed_results);
        $this->childIds = array_merge($this->childIds, $child_ids);
      }
    }

    return $keyed_results;
  }

}

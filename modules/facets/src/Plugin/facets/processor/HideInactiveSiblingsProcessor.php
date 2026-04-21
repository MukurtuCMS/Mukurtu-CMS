<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor that hides results of inactive siblings.
 *
 * @FacetsProcessor(
 *   id = "hide_inactive_siblings_processor",
 *   label = @Translation("Hide inactive siblings"),
 *   description = @Translation("In case that a result item belongs to different branches of a hierarchy it the hierarchical facet might show inactive fragments of other branches. This filter will hide these fragments. It could be combined with 'Show siblings' to only display the siblings of active items. In case of a non-hierarchical facet, all non-active options will be hidden after the selection has been made."),
 *   stages = {
 *     "build" = 10
 *   }
 * )
 */
class HideInactiveSiblingsProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $facet_results = $facet->getResults();
    $active_items = $facet->getActiveItems();

    if ($facet->getUseHierarchy()) {
      $hierarchy = $facet->getHierarchyInstance();
      $facet->addCacheableDependency($hierarchy);

      if (!$facet->getKeepHierarchyParentsActive()) {
        $parents_of_active_items = [];
        foreach ($active_items as $active_item) {
          $parents_of_active_items = array_merge($parents_of_active_items, $hierarchy->getParentIds($active_item));
        }
        $active_items = array_unique(array_merge($active_items, $parents_of_active_items));
      }

      $siblings = $hierarchy->getSiblingIds($active_items);
      $siblings_and_their_childs = array_merge($siblings, array_merge(...array_values($hierarchy->getChildIds($siblings))));

      foreach ($facet_results as $id => $result) {
        if (in_array($result->getRawValue(), $siblings_and_their_childs, FALSE) && !$result->isActive()) {
          unset($results[$id]);
        }
      }
    }
    elseif ($active_items) {
      foreach ($facet_results as $id => $result) {
        if (!$result->isActive()) {
          unset($results[$id]);
        }
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    return $facet->getFacetType() == 'facet_entity';
  }

}

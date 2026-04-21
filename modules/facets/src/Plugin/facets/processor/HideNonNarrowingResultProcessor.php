<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor that hides results that don't narrow results.
 *
 * @FacetsProcessor(
 *   id = "hide_non_narrowing_result_processor",
 *   label = @Translation("Hide non-narrowing results"),
 *   description = @Translation("Only display items that will narrow the results."),
 *   stages = {
 *     "build" = 40
 *   }
 * )
 */
class HideNonNarrowingResultProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $facetSource = $facet->getFacetSource();

    if ($facetSource) {
      $result_count = $facetSource->getCount();
    }
    else {
      // @todo Backward compatibility, should be removed!
      $facet_results = $facet->getResults();
      $result_count = 0;
      foreach ($facet_results as $result) {
        if ($result->isActive()) {
          $result_count += $result->getCount();
        }
      }
    }

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $id => $result) {
      $children_results = $result->getChildren();

      if ($children_results) {
        $reduced_children_results = $this->build($facet, $children_results);
        $result->setChildren($reduced_children_results);
        if ($reduced_children_results) {
          continue;
        }
      }

      if ((($result->getCount() == $result_count) || ($result->getCount() == 0)) && !$result->isActive() && !$result->hasActiveChildren()) {
        unset($results[$id]);
      }
    }

    return $results;
  }

}

<?php

namespace Drupal\facets_processors_collection\Plugin\facets\processor;

use Drupal\facets\Result\Result;

/**
 * A processor that does "random sort" plugin.
 *
 * @FacetsProcessor(
 *   id = "fpc_sort_random_processor",
 *   label = @Translation("FPC: random sorting"),
 *   description = @Translation("Randomly sorts result, <em>disables cache</em>"),
 *   stages = {
 *     "sort" = 50
 *   }
 * )
 */
class FpcSortRandomProcessor extends FpcSortProcessor {

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b) {
    return random_int(-1, 1);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // As sorting should be random, we can't cache results.
    return 0;
  }

}

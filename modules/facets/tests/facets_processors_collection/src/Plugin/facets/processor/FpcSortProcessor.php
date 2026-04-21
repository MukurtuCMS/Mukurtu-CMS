<?php

namespace Drupal\facets_processors_collection\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * A processor that emulates sort plugin, but does nothing with ordering.
 *
 * @FacetsProcessor(
 *   id = "fpc_sort_processor",
 *   label = @Translation("FPC: Sort test processor"),
 *   description = @Translation("Does nothing."),
 *   stages = {
 *     "sort" = 50
 *   }
 * )
 */
class FpcSortProcessor extends SortProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b) {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['fpc:sort_processor'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['fpc_sort'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

}

<?php

namespace Drupal\facets_processors_collection\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\PostQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Dummy post query processor plugin to test plugin.manager cacheability.
 *
 * @FacetsProcessor(
 *   id = "fpc_post_query_processor",
 *   label = @Translation("FPC: Post query plugin"),
 *   description = @Translation("Does nothing."),
 *   stages = {
 *     "post_query" = 50
 *   }
 * )
 */
class FpcPostQueryProcessor extends ProcessorPluginBase implements PostQueryProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['fpc:post_query_processor']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['fpc_post_query']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function postQuery(FacetInterface $facet) {
    $facet->addCacheTags(['fpc:added_within_postQuery_method']);
  }

}

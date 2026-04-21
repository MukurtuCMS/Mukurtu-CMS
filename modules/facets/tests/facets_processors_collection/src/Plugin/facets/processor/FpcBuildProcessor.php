<?php

namespace Drupal\facets_processors_collection\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Dummy build processor plugin to test plugin.manager cacheability.
 *
 * @FacetsProcessor(
 *   id = "fpc_build_processor",
 *   label = @Translation("FPC: Build test processor"),
 *   description = @Translation("Adds 'test' prefix to each facet item display."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class FpcBuildProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $result) {
      $result->setDisplayValue('Test ' . $result->getDisplayValue());
    }
    // An example cache tag that can be added from the ::build().
    $facet->addCacheTags(['fpc:added_within_build_method']);

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['fpc:build_processor']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['fpc_build']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

}

<?php

namespace Drupal\facets_query_processor\Plugin\facets\url_processor;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\url_processor\QueryString;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Query string URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "dummy_query",
 *   label = @Translation("Dummy query"),
 *   description = @Translation("Dummy for testing.")
 * )
 */
class DummyQuery extends QueryString {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $eventDispatcher, FacetsUrlGenerator $urlGenerator) {
    // Override the default separator.
    $configuration['separator'] = '||';
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager, $eventDispatcher, $urlGenerator);
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {
    $facet->addCacheTags(['dummy_query_build_urls_tag']);
    $facet->addCacheContexts(['dummy_query_build']);
    return parent::buildUrls($facet, $results);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['dummy_query_pre_query_tag']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['dummy_query_pre_query']);
  }

}

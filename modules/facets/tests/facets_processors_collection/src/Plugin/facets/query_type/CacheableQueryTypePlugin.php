<?php

namespace Drupal\facets_processors_collection\Plugin\facets\query_type;

use Drupal\facets\Plugin\facets\query_type\SearchApiString;

/**
 * Extends base search_api_string plugin with custom cacheability.
 *
 * @FacetsQueryType(
 *   id = "search_api_string_cached",
 *   label = @Translation("FPC: cached string"),
 * )
 */
class CacheableQueryTypePlugin extends SearchApiString {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    parent::execute();

    $this->query->addCacheTags(['fpc:query_plugin_type_plugin']);
    $this->query->addCacheContexts(['fpc_query_type_plugin']);
  }

}

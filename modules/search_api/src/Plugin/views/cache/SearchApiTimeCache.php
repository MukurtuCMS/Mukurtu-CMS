<?php

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\Time;

/**
 * Defines a time-based cache plugin for use with Search API views.
 *
 * This cache plugin will set a predefined cache lifetime, intended for search
 * views. The view will be refreshed after the configured time period has
 * passed.
 *
 * Use this for search results views that are using external search engines such
 * as Apache Solr which are updated asynchronously from Drupal. Also use it if
 * you are showing search results which come from different sources, such as
 * multi-site search, or searches that include external data.
 *
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'search_api_time',
  title: new TranslatableMarkup('Search API (time-based)'),
  help: new TranslatableMarkup('Cache results for a predefined time period. Useful for sites that use external search engines such as Solr, or index multiple datasources. <strong>Caution:</strong> Will lead to stale results and might harm performance for complex search pages.'),
)]
class SearchApiTimeCache extends Time {

  use SearchApiCachePluginTrait;

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    // Do not invalidate time-based cache if any items on the index are indexed
    // or deleted.
    $key = array_search('search_api_list:' . $this->getQuery()->getIndex()->id(), $tags, TRUE);
    if ($key !== FALSE) {
      unset($tags[$key]);
    }
    return array_values($tags);
  }

}

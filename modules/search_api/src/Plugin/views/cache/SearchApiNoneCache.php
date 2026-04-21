<?php

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\None;
use Drupal\views\ResultRow;

/**
 * Caching plugin that provides no caching at all for use with Search API views.
 *
 * This cache plugin fixes a performance issue when disabling caching on Search
 * API views when cache metadata is added to row items.
 *
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'search_api_none',
  title: new TranslatableMarkup('Search API (none)'),
  help: new TranslatableMarkup('No caching of Views data.'),
)]
class SearchApiNoneCache extends None {

  /**
   * {@inheritdoc}
   */
  public function getRowId(ResultRow $row) {
    return $row->search_api_id;
  }

}

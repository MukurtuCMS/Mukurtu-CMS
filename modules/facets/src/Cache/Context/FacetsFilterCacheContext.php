<?php

namespace Drupal\facets\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;

/**
 * Defines the FacetsFilterCacheContext service, for per facets args caching.
 *
 * Cache context ID: 'facets_filter' (to vary by all request arguments).
 * Calculated cache context ID: 'facets_filter:%key', e.g.'facets_filter:f'
 * (to vary by the 'f' filter key).
 */
class FacetsFilterCacheContext extends RequestStackCacheContextBase implements CalculatedCacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Facets filter');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($arg = NULL) {
    if ($arg === NULL) {
      // All arguments requested. Use normalized query string to minimize
      // variations.
      $value = $this->requestStack->getCurrentRequest()->request;
      return http_build_query($value);
    }
    elseif ($this->requestStack->getCurrentRequest()->request->has($arg)) {
      $value = $this->requestStack->getCurrentRequest()->request->all()[$arg] ?? NULL;
      if (is_array($value)) {
        return http_build_query($value);
      }
      elseif ($value !== '') {
        return $value;
      }
      return '?valueless?';
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($arg = NULL) {
    return new CacheableMetadata();
  }

}

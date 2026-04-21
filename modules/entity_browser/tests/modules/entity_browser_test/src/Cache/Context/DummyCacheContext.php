<?php

namespace Drupal\entity_browser_test\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;

/**
 * Dummy cache context for Entity browser test purposes.
 *
 * Cache context ID: 'eb_dummy'.
 */
class DummyCacheContext implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Dummy context');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return "dummy";
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}

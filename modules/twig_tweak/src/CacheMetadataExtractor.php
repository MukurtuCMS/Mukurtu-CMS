<?php

namespace Drupal\twig_tweak;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\Element;

/**
 * Cache metadata extractor service.
 */
class CacheMetadataExtractor {

  /**
   * Extracts cache metadata from object or render array.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|array $input
   *   The cacheable object or render array.
   *
   * @return array
   *   A render array with extracted cache metadata.
   */
  public function extractCacheMetadata($input): array {
    if ($input instanceof CacheableDependencyInterface) {
      $cache_metadata = CacheableMetadata::createFromObject($input);
    }
    elseif (is_array($input)) {
      $cache_metadata = self::extractFromArray($input);
    }
    else {
      $message = sprintf('The input should be either instance of %s or array. %s was given.', CacheableDependencyInterface::class, \get_debug_type($input));
      throw new \InvalidArgumentException($message);
    }

    $build = [];
    $cache_metadata->applyTo($build);
    return $build;
  }

  /**
   * Extracts cache metadata from renders array.
   */
  private static function extractFromArray(array $build): CacheableMetadata {
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $keys = Element::children($build);
    foreach (array_intersect_key($build, array_flip($keys)) as $item) {
      $cache_metadata->addCacheableDependency(self::extractFromArray($item));
    }
    return $cache_metadata;
  }

}

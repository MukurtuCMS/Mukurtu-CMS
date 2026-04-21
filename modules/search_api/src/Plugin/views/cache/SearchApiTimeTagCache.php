<?php

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\views\Attribute\ViewsCache;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Plugin\views\cache\Time;
use Drupal\views\ResultRow;

/**
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'search_api_time_tag',
  title: new TranslatableMarkup('Search API (time and tag-based)'),
  help: new TranslatableMarkup('Cache results for a predefined time period or until the associated cache tags are invalidated.'),
)]
class SearchApiTimeTagCache extends Time {

  /**
   * {@inheritdoc}
   */
  public function getRowCacheTags(ResultRow $row) {
    $tags = [];

    foreach ($row->_relationship_objects as $objects) {
      /** @var \Drupal\Core\TypedData\ComplexDataInterface $object */
      foreach ($objects as $object) {
        $entity = $object->getValue();
        if ($entity instanceof EntityInterface) {
          $tags = Cache::mergeTags($tags, $entity->getCacheTags());
        }
      }
    }

    return $tags;
  }

}

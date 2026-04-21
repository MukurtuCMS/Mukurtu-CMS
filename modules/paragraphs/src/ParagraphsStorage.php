<?php

namespace Drupal\paragraphs;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Custom storage handler for paragraphs.
 */
class ParagraphsStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids) {
    $entities = parent::loadMultipleRevisions($revision_ids);
    // If the ignore static revision cache property exists, then also add default revisions to the
    // regular entity static cache. This is not done by default as there might be complications with wprkspace
    // preloading of non-default revisions, this not done for paragraphs.
    if (property_exists($this, 'ignoreStaticRevisionCache') && !$this->ignoreStaticRevisionCache) {
      foreach ($entities as $entity) {
        if ($entity->isDefaultRevision()) {
          $this->memoryCache->set($this->buildCacheId($entity->id()), $entity, MemoryCacheInterface::CACHE_PERMANENT, [$this->memoryCacheTag]);
        }
      }
    }
    return $entities;
  }

}

<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\mukurtu_dictionary\Entity\WordListInterface;
use Drupal\Core\Cache\Cache;

class WordList extends Node implements WordListInterface {
  const WORDS_FIELD = 'field_words';

  /**
   * {@inheritdoc}
   */
  public function add(EntityInterface $entity): void {
    // Add the new entity to the entity ref field.
    $items = $this->get(self::WORDS_FIELD)->getValue();
    $items[] = ['target_id' => $entity->id()];
    $this->set(self::WORDS_FIELD, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(EntityInterface $entity): void {
    $needle = $entity->id();
    $items = $this->get(self::WORDS_FIELD)->getValue();
    foreach ($items as $delta => $item) {
      if ($item['target_id'] == $needle) {
        unset($items[$delta]);
      }
    }
    $this->set(self::WORDS_FIELD, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(): int {
    $items = $this->get(self::WORDS_FIELD)->getValue();
    if (is_countable($items)) {
      return count($items);
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Invalid the cache of referenced entities
    // to trigger recalculation of the computed fields.
    $refs = $this->get(self::WORDS_FIELD)->referencedEntities() ?? NULL;
    if (!empty($refs)) {
      foreach ($refs as $ref) {
        Cache::invalidateTags($ref->getCacheTagsToInvalidate());
      }
    }

    // Invalid the word lists cache as well.
    Cache::invalidateTags($this->getCacheTagsToInvalidate());
  }

}

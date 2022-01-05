<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mukurtu_dictionary\Entity\WordListInterface;

class WordList extends Node implements WordListInterface {
  const WORDS_FIELD = 'field_words';

  /**
   * Add a word to the word list.
   *
   * @param EntityInterface $entity
   *   The word entity to add to the word list.
   *
   * @return void
   */
  public function add(EntityInterface $entity): void {
    $items = $this->get(self::WORDS_FIELD)->getValue();
    $items[] = ['target_id' => $entity->id()];
    $this->set(self::WORDS_FIELD, $items);
  }

  /**
   * Remove a word from the word list.
   *
   * @param EntityInterface $entity
   *   The word to remove from the word list.
   *
   * @return void
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

}

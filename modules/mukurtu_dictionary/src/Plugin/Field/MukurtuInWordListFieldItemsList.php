<?php

namespace Drupal\mukurtu_dictionary\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mukurtu_dictionary\Entity\WordList;

class MukurtuInWordListFieldItemsList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->ensurePopulated();
  }

  protected function ensurePopulated() {
    $entity = $this->getEntity();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'word_list')
      ->condition(WordList::WORDS_FIELD, $entity->id())
      ->condition('status', TRUE);
    $results = $query->execute();

    if (!empty($results)) {
      $delta = 0;
      foreach ($results as $result) {
        $this->list[$delta] = $this->createItem($delta, $result);
        $delta++;
      }
    }
  }

}

<?php

namespace Drupal\mukurtu_collection\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class MukurtuInCollectionFieldItemsList extends EntityReferenceFieldItemList {

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
      ->condition('type', 'collection')
      ->condition(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $entity->id())
      ->accessCheck(TRUE)
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

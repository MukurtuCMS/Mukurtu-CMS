<?php

namespace Drupal\mukurtu_collection\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mukurtu_collection\Entity\Collection;

class MukurtuParentCollectionFieldItemsList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->ensurePopulated();
  }

  protected function ensurePopulated() {
    $entity = $this->getEntity();
    $parent = NULL;
    if ($entity instanceof Collection) {
      $parent = $entity->getParentCollectionId();
    }

    if ($parent) {
      $this->list[0] = $this->createItem(0, $parent);
    } else {
      $this->list = [];
    }
  }

}

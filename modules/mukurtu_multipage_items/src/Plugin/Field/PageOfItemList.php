<?php

namespace Drupal\mukurtu_multipage_items\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * PageOfItemList class to generate a computed field.
 */
class PageOfItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->ensurePopulated();
  }

  /**
   * {@inheritdoc}
   */
  protected function ensurePopulated() {
    $mpiManager = \Drupal::service('mukurtu_multipage_items.multipage_item_manager');

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getEntity();

    $mpi = $mpiManager->getMultipageEntity($entity);

    $delta = 0;
    $this->list = [];
    if ($mpi) {
      $this->list[] = $this->createItem($delta++, $mpi->id());
    }
  }

}

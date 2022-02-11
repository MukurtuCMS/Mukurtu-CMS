<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class CommunityProtocolsItemList extends EntityReferenceFieldItemList {

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
      ->condition('type', 'protocol')
      ->condition(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY, $entity->id())
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

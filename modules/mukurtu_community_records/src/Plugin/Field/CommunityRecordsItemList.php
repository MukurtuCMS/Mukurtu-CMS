<?php

namespace Drupal\mukurtu_community_records\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * TermStatusItemList class to generate a computed field.
 */
class CommunityRecordsItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();

    // Do we have the required fields?
    if (!($entity->hasField(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)
      && $entity->hasField(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_COMMUNITY_RECORDS))) {
      return;
    }

    // Find the parent node.
    $parent = $entity->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)->referencedEntities()[0] ?? NULL;
    if (empty($parent)) {
      $parent = $entity;
    }

    // Add the parent (first) record.
    $this->list[] = $this->createItem(0, $parent->id());

    // Add all the children records.
    $children = $parent->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_COMMUNITY_RECORDS);
    if (!empty($children)) {
      foreach ($children as $child) {
        $this->list[] = $this->createItem(0, $child->target_id);
      }
    }
  }

}

<?php

namespace Drupal\mukurtu_community_records\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CommunityRecordsItemList class to generate a computed field.
 */
class CommunityRecordsItemList extends EntityReferenceFieldItemList {
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
    $entity = $this->getEntity();
    $records = $this->getAllRecords($entity);

    foreach ($records as $delta => $nid) {
      $this->list[] = $this->createItem($delta, $nid);
    }
  }

  /**
   * Find all records associated with an entity.
   */
  protected function getAllRecords($entity) {
    $original_record = NULL;

    // The owning entity is always record #1.
    $records = [$entity->id()];

    if (mukurtu_community_records_has_record_field($entity, MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)) {
      // Is entity a community record or the original record?
      $original_record = $entity->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)->referencedEntities()[0] ?? NULL;

      if (empty($original_record)) {
        $original_record = $entity;
      } else {
        $records[] = $original_record->id();
      }

      // Find all the community records for the original record.
      $query = \Drupal::entityQuery('node')
        ->condition(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD, $original_record->id())
        ->sort('created', 'DESC');
      $results = $query->execute();

      // Add the other records.
      // TODO: Default ordering would be established here.
      foreach ($results as $vid => $nid) {
        if ($nid === $entity->id()) {
          continue;
        }
        $records[] = $nid;
      }
    }

    return $records;
  }

}

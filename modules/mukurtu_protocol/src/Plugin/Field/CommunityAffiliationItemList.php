<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CommunityAffiliationItemList class to generate a computed field.
 */
class CommunityAffiliationItemList extends EntityReferenceFieldItemList {
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
    $communities = [];
    $entity = $this->getEntity();

    if ($entity instanceof FieldableEntityInterface) {
      if ($entity->hasField('field_protocol_control')) {
        // Get the protocol control entity.
        $pc = $entity->get('field_protocol_control')->referencedEntities();
        if (!empty($pc)) {
          // Get all the associated communities.
          $communities = $pc[0]->getCommunities();
        }
      }
    }

    foreach ($communities as $delta => $community) {
      $this->list[] = $this->createItem($delta, $community->id());
    }
  }

}

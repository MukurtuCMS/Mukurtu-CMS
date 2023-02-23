<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

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

    if ($entity instanceof CulturalProtocolControlledInterface) {
      // Get all the associated communities.
      $communities = $entity->getCommunities();
    }

    foreach ($communities as $delta => $community) {
      $this->list[] = $this->createItem($delta, $community->id());
    }
  }

}

<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\og\Og;

/**
 * UserCommunityMembershipItemList class to generate a computed field.
 */
class UserCommunityMembershipItemList extends EntityReferenceFieldItemList {
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
    /** @var \Drupal\Core\Session\AccountInterface $entity */
    $entity = $this->getEntity();

    $memberships = Og::getMemberships($entity);
    $delta = 0;

    $this->list = [];
    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() == 'community') {
        $this->list[] = $this->createItem($delta++, $membership->getGroupId());
      }
    }
  }

}

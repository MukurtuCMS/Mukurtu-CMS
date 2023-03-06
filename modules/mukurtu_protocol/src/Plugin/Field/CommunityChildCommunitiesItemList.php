<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CommunityChildCommunitiesItemList class to generate a computed field.
 */
class CommunityChildCommunitiesItemList extends EntityReferenceFieldItemList {
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
    $config = \Drupal::config('mukurtu_protocol.community_organization');
    $org = $config->get('organization');

    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
    $community = $this->getEntity();

    $children = [];

    foreach ($org as $id => $setting) {
      if ($setting['parent'] == $community->id()) {
        $children[$setting['weight']] = $id;
      }
    }

    $delta = 0;
    $this->list = [];
    foreach ($children as $child) {
      $this->list[] = $this->createItem($delta++, $child);
    }
  }

}

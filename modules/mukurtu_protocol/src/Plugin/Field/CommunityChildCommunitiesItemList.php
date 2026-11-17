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

    $this->list = [];

    // An unsaved community has no children. Its ID is NULL, and NULL loosely
    // equals the 0 that marks a top-level community, so the comparison below
    // would otherwise list every top-level community as its child.
    if ($community->isNew() || $community->id() === NULL) {
      return;
    }

    $children = [];

    foreach ($org ?? [] as $id => $setting) {
      if ((int) ($setting['parent'] ?? 0) === (int) $community->id()) {
        $children[$setting['weight']] = $id;
      }
    }

    $delta = 0;
    foreach ($children as $child) {
      $this->list[] = $this->createItem($delta++, $child);
    }
  }

}

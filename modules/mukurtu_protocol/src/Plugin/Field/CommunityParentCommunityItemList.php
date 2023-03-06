<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CommunityParentCommunityItemList class to generate a computed field.
 */
class CommunityParentCommunityItemList extends EntityReferenceFieldItemList {
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

    $parent_id = NULL;
    if (isset($org[$community->id()])) {
      $parent_id = $org[$community->id()]['parent'] ?? NULL;
    }

    if ($parent_id == 0) {
      $parent_id = NULL;
    }

    $this->list = [];
    if ($parent_id) {
      $this->list[] = $this->createItem(0, $parent_id);
    }
  }

}

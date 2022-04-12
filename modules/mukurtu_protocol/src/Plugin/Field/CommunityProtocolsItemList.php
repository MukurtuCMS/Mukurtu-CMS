<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CommunityProtocolsItemList class to generate a computed field.
 */
class CommunityProtocolsItemList extends EntityReferenceFieldItemList {
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
    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
    $community = $this->getEntity();

    // Find protocols that use this community.
    $protocols = $community->getProtocols();

    $delta = 0;
    $this->list = [];
    foreach ($protocols as $protocol) {
      $this->list[] = $this->createItem($delta++, $protocol->id());
    }
  }

}

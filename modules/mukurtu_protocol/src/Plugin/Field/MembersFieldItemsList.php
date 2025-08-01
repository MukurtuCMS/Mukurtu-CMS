<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class MembersFieldItemsList extends EntityReferenceFieldItemList
{
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue()
  {
    $this->ensurePopulated();
  }

  protected function ensurePopulated()
  {
    $entity = $this->getEntity();
    /** @var \Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface $entity */
    $members = $entity->getMembersList();

    if (!empty($members)) {
      $delta = 0;
      foreach ($members as $member) {
        $this->list[$delta] = $this->createItem($delta, $member);
        $delta++;
      }
    }
  }
}

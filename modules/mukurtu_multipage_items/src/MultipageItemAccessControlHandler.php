<?php

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Multipage Item entity.
 *
 * @see \Drupal\mukurtu_multipage_items\Entity\MultipageItem.
 */
class MultipageItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemInterface $entity */

    // First page controls access to the book.
    $firstPage = $entity->getFirstPage();
    if (!$firstPage) {
      return parent::checkAccess($entity, $operation, $account);
    }
    return $firstPage->access($operation, $account, TRUE)->andIf(parent::checkAccess($entity, $operation, $account));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // @todo TBD.
    return AccessResult::allowed();
  }

}

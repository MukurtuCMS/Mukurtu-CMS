<?php

namespace Drupal\mukurtu_multipage_items;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\NodeInterface;

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
    if (!$entity instanceof MultipageItemInterface) {
      return AccessResult::neutral();
    }

    // First page controls access to the multipage item.
    $firstPage = $entity->getFirstPage();
    if (!$firstPage) {
      return parent::checkAccess($entity, $operation, $account);
    }
    return $firstPage->access($operation, $account, TRUE)
      ->orIf(parent::checkAccess($entity, $operation, $account));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $node = $context['node'] ?? NULL;
    if (!$node instanceof NodeInterface && !$node instanceof CulturalProtocolControlledInterface) {
      return AccessResult::neutral()->addCacheableDependency($node);
    }
    // If the account has permission globally to administer multipage items,
    // grant access.
    if ($account->hasPermission('administer multipage item')) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }
    // Check for the multipage item admin permission in one of the owning
    // protocols.
    if ($protocols = $node->getProtocolEntities()) {
      foreach ($protocols as $protocol) {
        $membership = $protocol->getMembership($account);
        if ($membership && $membership->hasPermission('administer multipage item')) {
          return AccessResult::allowed()
            ->addCacheableDependency($node)
            ->addCacheableDependency($membership);
        }
      }
    }
    return AccessResult::forbidden()->addCacheableDependency($node);
  }

}

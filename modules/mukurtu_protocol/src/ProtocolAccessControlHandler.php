<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Protocol entity.
 *
 * @see \Drupal\mukurtu_protocol\Entity\Protocol.
 */
class ProtocolAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $entity */

    $ogAccessService = \Drupal::service('og.access');
    // @todo Return after implementing community.
    return AccessResult::allowed();
   // $community = $entity->getCommunity();
    if ($community) {
      return $ogAccessService->userAccessGroupContentEntityOperation($operation, $community, $entity, $account);
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add protocol entities');
  }


}

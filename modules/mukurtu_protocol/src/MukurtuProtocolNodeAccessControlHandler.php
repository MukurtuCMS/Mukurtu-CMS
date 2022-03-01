<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeAccessControlHandler;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;

/**
 * Access controller for node entities under Mukurtu protocol control.
 *
 * @see \Drupal\mukurtu_protocol\Entity\ProtocolControl.
 */
class MukurtuProtocolNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\node\NodeInterface $entity */

    // Exit early if this entity doesn't have a protocol field.
    if (!$entity->hasField('field_protocol_control')) {
      // Fall back to normal node access checks.
      return parent::checkAccess($entity, $operation, $account);
    }

    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $protocolControlEntity */
    // Try to load the protocol control entity.
    $protocolControlEntity = ProtocolControl::getProtocolControlEntity($entity);

    // No valid protocol control entity (but DOES have a protocol control
    // field) or an empty protocol list. Here we'll default to owner
    // only for everything.
    if (!$protocolControlEntity || empty($protocolControlEntity->getProtocols())) {
      if ($entity->getOwnerId() == $account->id()) {
        return parent::checkAccess($entity, $operation, $account);
      }
      return AccessResult::forbidden();
    }

    // At this point we have a node with a valid, non-empty
    // protocol control entity.
    // For non-members we can deny immediately.
    if (!$protocolControlEntity->inGroup($account)) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        return parent::checkAccess($entity, $operation, $account);

      case 'update':
      case 'delete':
        // Ask each member OG group about specific permissions.
        $ogAccessService = \Drupal::service('og.access');
        $protocols = $protocolControlEntity->getMemberProtocols($account);

        // Our initial result needs to be "allowed" for all, "neutral" for any.
        // Check the truth tables on AccessResult orIf/andIf for why.
        $result = ($protocolControlEntity->getPrivacySetting() == 'all') ? AccessResult::allowed() : AccessResult::neutral();
        $modeFn = ($protocolControlEntity->getPrivacySetting() == 'any') ? 'orIf' : 'andIf';

        // Check each protocol.
        foreach ($protocols as $protocol) {
          $result = $result->{$modeFn}($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
        }

        // Protocols are very opinionated, neutral is not good enough for
        // update/delete, allowed is required.
        return $result->isNeutral() ? AccessResult::forbidden() : $result;
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    /*
     * To create a node of type entity_bundle, the account needs at least
     * one protocol membership that grants the 'Create new' permission
     * for that entity_bundle.
     */
    $memberships = Og::getMemberships($account);

    foreach ($memberships as $membership) {
      if ($membership->hasPermission("create $entity_bundle content")) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}

<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\user\EntityOwnerInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\og\Og;
use Drupal\og\OgAccess;

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

    // Try to load the protocol control entity.
    $fieldProtocolControl = $entity->get('field_protocol_control')->getValue();
    $protocolControlId = array_column($fieldProtocolControl, 'target_id');

    if (empty($protocolControlId)) {
      $protocolControlEntity = NULL;
    }
    else {
      $protocolControlEntity = \Drupal::entityTypeManager()->getStorage('protocol_control')->load($protocolControlId[0]);
    }
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $protocolControlEntity */

    // No protocol control entity (but DOES have a protocol control field)
    // or an invalid reference. Here we'll default to owner only for everything.
    if (!$protocolControlEntity) {
      if ($entity->getOwnerId() == $account->id()) {
        return parent::checkAccess($entity, $operation, $account);
      }
      return AccessResult::forbidden();
    }

    // At this point we have a node with a valid, non-empty
    // protocol control entity.
    // For non-members we can deny immediately.
    if (!$protocolControlEntity->inGroup($account)) {
      dpm("PCE non-member, early denial");
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        return parent::checkAccess($entity, $operation, $account);

      case 'update':
      case 'delete':
        $ogAccessService = \Drupal::service('og.access');
        $result = AccessResult::neutral();
        $protocols = $protocolControlEntity->getMemberProtocols($account);
        if ($protocolControlEntity->getPrivacySetting() == 'all') {
          dpm("all");
          foreach ($protocols as $protocol) {
            $result = $result->andIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
            dpm("asking OG about protocol {$protocol->getName()} for operation {$operation} on entity {$entity->getTitle()}");
            //dpm($result);
          }
        }
        else {
          dpm("any");
          foreach ($protocols as $protocol) {
            $result = $result->orIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
            dpm("asking OG about protocol {$protocol->getName()} for operation {$operation} on entity {$entity->getTitle()}");
            //dpm($result);
          }
        }
        return $result;
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add protocol control entities');
  }

}

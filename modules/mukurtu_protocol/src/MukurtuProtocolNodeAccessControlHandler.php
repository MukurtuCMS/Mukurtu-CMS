<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeAccessControlHandler;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Access controller for node entities under Mukurtu protocol control.
 */
class MukurtuProtocolNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\node\NodeInterface $entity */

    // Exit early if this entity doesn't implement cultural protocols.
    if (!($entity instanceof CulturalProtocolControlledInterface)) {
      // Fall back to normal node access checks.
      return parent::checkAccess($entity, $operation, $account);
    }

    // For an empty protocol set, default to owner only for everything.
    if (empty($entity->getProtocols())) {
      if ($entity->getOwnerId() == $account->id()) {
        return parent::checkAccess($entity, $operation, $account);
      }
      return AccessResult::forbidden();
    }

    // For non-members we can deny immediately.
    if (!$entity->isProtocolSetMember($account)) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
      case 'view all revisions':
        return parent::checkAccess($entity, $operation, $account);

      case 'update':
      case 'delete':
        $ogAccessService = \Drupal::service('og.access');

        if ($entity->getSharingSetting() == 'all') {
          // For 'all', every protocol must individually grant edit access.
          // We use andIf starting from allowed; neutral (no edit permission) is
          // treated as forbidden since every single protocol must grant access.
          // Cache metadata from each OG result is preserved via addCacheableDependency.
          $protocolEntities = $entity->getProtocolEntities();
          if (empty($protocolEntities)) {
            // Broken protocol references — deny conservatively.
            return AccessResult::forbidden();
          }
          $result = AccessResult::allowed();
          foreach ($protocolEntities as $protocol) {
            $protocolResult = $ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account);
            if ($protocolResult->isNeutral()) {
              $protocolResult = AccessResult::forbidden()->addCacheableDependency($protocolResult);
            }
            $result = $result->andIf($protocolResult);
          }
        }
        else {
          // For 'any', one protocol granting edit access is sufficient.
          // getMemberProtocols() ensures the user is a member of at least one
          // protocol (guaranteed by isProtocolSetMember() above).
          $result = AccessResult::neutral();
          foreach ($entity->getMemberProtocols($account) as $protocol) {
            $result = $result->orIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
          }
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
    // If the bundle class has its own check create access, run that and we will
    // include that in the overall access check for the bundle.
    $bundleClass = $this->entityTypeManager->getStorage('node')->getEntityClass($entity_bundle);
    $bundleCheckResult = AccessResult::allowed();
    if ($bundleClass) {
      $interfaces = class_implements($bundleClass);
      if (isset($interfaces['Drupal\mukurtu_core\Entity\BundleSpecificCheckCreateAccessInterface'])) {
        $bundleCheckResult = $bundleClass::bundleCheckCreateAccess($account, $context);
      }
    }

    /*
     * To create a node of type entity_bundle, the account needs at least
     * one protocol membership that grants the 'Create new' permission
     * for that entity_bundle.
     */
    $memberships = Og::getMemberships($account);

    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }

      // Account must be permitted to use the protocol on content.
      if (!$membership->hasPermission("apply protocol")) {
        continue;
      }

      // Account must have create permission for the given type.
      if ($membership->hasPermission("create $entity_bundle content")) {
        return AccessResult::allowedIf($bundleCheckResult->isAllowed());
      }
    }
    return AccessResult::forbidden();
  }

}

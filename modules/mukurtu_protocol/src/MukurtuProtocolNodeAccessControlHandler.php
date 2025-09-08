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
        // Ask each member OG group about specific permissions.
        $ogAccessService = \Drupal::service('og.access');
        $protocols = $entity->getMemberProtocols($account);

        // By this point, we have already taken the sharing setting into account
        // with the call to isProtocolSetMember() above, which guarantees that
        // if the sharing setting is 'any', the user is a member of at least one
        // of the protocols, and if it is 'all', the user is a member of all the
        // protocols.

        // Given this, the access result for each protocol must be combined
        // using orIf. Previously, orIf was only used for entities under the
        // 'any' sharing setting; andIf was used for 'all'. Using andIf for
        // 'all' was denying legitimate access in the case where a user has a
        // mix of highly privileged roles and lesser privileged roles, i.e., if
        // the user was both a protocol steward of one protocol and a protocol
        // member of another and both these protocols were applied to an entity
        // under the 'all' setting. The access check on the protocol where the
        // user was merely a protocol member returned neutral(), which, when
        // andIfed with the protocol steward access of allowed(), returned
        // neutral(). We don't grant access if the result is only neutral(), so
        // access was denied in this case.

        // The correct thing to do is to consider the user's most privileged
        // permissions for access, hence the change to using orIf for 'all'.
        $result = AccessResult::neutral();

        foreach ($protocols as $protocol) {
          $result = $result->orIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
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

<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeAccessControlHandler;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\og\GroupContentOperationPermission;

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
        $permissionManager = \Drupal::service('og.permission_manager');
        $protocols = $entity->getMemberProtocols($account);

        // Our initial result needs to be "allowed" for all, "neutral" for any.
        // Check the truth tables on AccessResult orIf/andIf for why.
        $sharingSetting = $entity->getSharingSetting();
        $result = ($sharingSetting == 'all') ? AccessResult::allowed() : AccessResult::neutral();

        if ($sharingSetting == 'any') {
          foreach ($protocols as $protocol) {
            $result = $result->orIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
          }
        }
        else {
          foreach ($protocols as $protocol) {
            // This is lifted from
            // OgEventSubscriber::checkGroupContentEntityOperationAccess().
            // We needed a way to compare access across all the user's protocol
            // memberships. This context was getting lost in the call stack of
            // userAccessGroupContentEntityOperation(), which was comparing each
            // protocol membership in a vacuum.
            $group_entity_type_id = $protocol->getEntityTypeId();
            $group_bundle_id = $protocol->bundle();
            $group_content_bundle_ids = [$entity->getEntityTypeId() => [$entity->bundle()]];
            // Check if the user owns the entity which is being operated on.
            $is_owner = $entity instanceof EntityOwnerInterface && $entity->getOwnerId() == $account->id();

            $permissions = $permissionManager->getDefaultEntityOperationPermissions($group_entity_type_id, $group_bundle_id, $group_content_bundle_ids);

            // Filter the permissions by operation and ownership.
            // If the user does not own the group content, only the non-owner permission
            // is relevant (for example 'edit any article node'). However when the user
            // _is_ the owner, then both permissions are relevant: an owner will have
            // access if they either have the 'edit any article node' or the 'edit own
            // article node' permission.
            $ownerships = $is_owner ? [FALSE, TRUE] : [FALSE];
            $permissions = array_filter($permissions, function (GroupContentOperationPermission $permission) use ($operation, $ownerships) {
              return $permission->getOperation() === $operation && in_array($permission->getOwner(), $ownerships);
            });

            // Retrieve the group content entity operation permissions.
            if ($permissions) {
              foreach ($permissions as $permission) {
                $result = $result->orIf($ogAccessService->userAccess($protocol, $permission->getName(), $account));
              }
            }
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

<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeAccessControlHandler;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\CulturalProtocols;

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
    // The decision depends on entity fields, so carry entity as cache dep.
    if (empty($entity->getProtocols())) {
      if ($entity->getOwnerId() == $account->id()) {
        return parent::checkAccess($entity, $operation, $account)
          ->addCacheableDependency($entity);
      }
      // Role-based node type permissions are sufficient when no protocols are
      // set -- same intent as checkCreateAccess(), which also honors them.
      $bundle = $entity->bundle();
      if ($operation === 'update' && $account->hasPermission("edit any $bundle content")) {
        return AccessResult::allowedIfHasPermission($account, "edit any $bundle content")
          ->addCacheableDependency($entity);
      }
      if ($operation === 'delete' && $account->hasPermission("delete any $bundle content")) {
        return AccessResult::allowedIfHasPermission($account, "delete any $bundle content")
          ->addCacheableDependency($entity);
      }
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // For non-members we can deny immediately. Depends on entity protocols and
    // account memberships; tag with entity and user:{id} so membership changes
    // (Community::addMember) or protocol field changes invalidate this result.
    if (!$entity->isProtocolSetMember($account)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheTags(["user:{$account->id()}"]);
    }

    switch ($operation) {
      case 'view':
      case 'view all revisions':
      case 'view revision':
      case 'revert revision':
      case 'delete revision':
        return parent::checkAccess($entity, $operation, $account);

      case 'update':
      case 'delete':
        // Ask each member OG group about specific permissions.
        $ogAccessService = \Drupal::service('og.access');
        $protocols = $entity->getMemberProtocols($account);

        // For 'all' sharing, the user must have update/delete permission in
        // EVERY protocol (use andIf, starting from allowed() as the identity).
        // A protocol steward in one protocol and a mere member in another is
        // not enough — they need sufficient permission across all of them.
        //
        // For 'any' sharing, the user's most-privileged role across any single
        // member protocol is sufficient (use orIf, starting from neutral()).
        //
        // Note: getMemberProtocols() includes open protocols as virtual members
        // even without explicit OG membership. For 'all' sharing this is fine:
        // userAccessGroupContentEntityOperation returns neutral() for a virtual
        // member with no OG role, so andIf(neutral()) correctly denies access.
        if ($entity->getSharingSetting() === 'all') {
          $result = AccessResult::allowed();
          foreach ($protocols as $protocol) {
            $result = $result->andIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
          }
        }
        else {
          $result = AccessResult::neutral();
          foreach ($protocols as $protocol) {
            $result = $result->orIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $protocol, $entity, $account));
          }
        }

        // Protocols are very opinionated, neutral is not good enough for
        // update/delete, allowed is required.
        return $result->isNeutral() ? AccessResult::forbidden()->addCacheableDependency($entity) : $result;
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $bundleClass = $this->entityTypeManager->getStorage('node')->getEntityClass($entity_bundle);
    $interfaces = $bundleClass ? class_implements($bundleClass) : [];

    // For bundles not under protocol control, use standard Drupal node access.
    if (!isset($interfaces['Drupal\mukurtu_protocol\CulturalProtocolControlledInterface'])) {
      return parent::checkCreateAccess($account, $context, $entity_bundle);
    }

    // For protocol-controlled bundles, also allow access via standard Drupal
    // node permissions (e.g. Mukurtu Manager with 'create landing_page content').
    // Intentional: returning here skips bundleCheckCreateAccess and protocol
    // membership checks — role-based permission is a sufficient grant on its own.
    $standardAccess = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($standardAccess->isAllowed()) {
      return $standardAccess;
    }

    // If the bundle class has its own check create access, run that and we will
    // include that in the overall access check for the bundle.
    $bundleCheckResult = AccessResult::allowed();
    if ($bundleClass && isset($interfaces['Drupal\mukurtu_core\Entity\BundleSpecificCheckCreateAccessInterface'])) {
      $bundleCheckResult = $bundleClass::bundleCheckCreateAccess($account, $context);
    }

    /*
     * To create a node of type entity_bundle, the account needs at least
     * one protocol membership that grants the 'Create new' permission
     * for that entity_bundle.
     */
    $memberships = Og::getMemberships($account);

    // Accumulate membership cache deps so role changes auto-invalidate, and
    // tag with user:{id} so new memberships (Community::addMember) do too.
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags(["user:{$account->id()}"]);

    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }

      $cacheability->addCacheableDependency($membership);

      // Skip if the user is blocked in any parent community.
      $protocol = $membership->getGroup();
      if (!$protocol || CulturalProtocols::isUserBlockedFromProtocolViaCommunity($account, $protocol)) {
        continue;
      }

      // Account must be permitted to use the protocol on content.
      if (!$membership->hasPermission("apply protocol")) {
        continue;
      }

      // Account must have create permission for the given type.
      if ($membership->hasPermission("create $entity_bundle content")) {
        return AccessResult::allowedIf($bundleCheckResult->isAllowed())
          ->addCacheableDependency($cacheability);
      }
    }
    return AccessResult::forbidden()->addCacheableDependency($cacheability);
  }

}

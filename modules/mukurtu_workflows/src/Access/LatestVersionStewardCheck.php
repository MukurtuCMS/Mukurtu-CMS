<?php

namespace Drupal\mukurtu_workflows\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\NodeInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check for /node/{node}/latest that extends core to allow stewards.
 *
 * Replaces content_moderation's _content_moderation_latest_version requirement
 * on the entity.node.latest_version route (see LatestVersionRouteSubscriber).
 * Replicates all existing LatestRevisionCheck logic and adds a fourth path:
 * protocol/language stewards may view the latest revision of content within
 * their protocols without needing the global 'view any unpublished content'
 * permission (which would bypass Mukurtu's node access grant system).
 */
class LatestVersionStewardCheck implements AccessInterface {

  public function __construct(
    protected ModerationInformationInterface $moderationInfo,
    protected MembershipManagerInterface $membershipManager,
  ) {}

  /**
   * Checks access to the latest-revision tab for a node.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $entity_type = $route->getOption('_content_moderation_entity_type');
    $entity = $entity_type ? $route_match->getParameter($entity_type) : NULL;

    if (!$entity || !$this->moderationInfo->hasPendingRevision($entity)) {
      return AccessResult::forbidden('No pending revision for moderated entity.')
        ->addCacheableDependency($entity ?? new \Drupal\Core\Cache\CacheableMetadata());
    }

    // Path 1 (core): view latest version + view any unpublished content.
    $access = AccessResult::allowedIfHasPermissions($account, ['view latest version', 'view any unpublished content']);
    if ($access->isAllowed()) {
      return $access->addCacheableDependency($entity);
    }

    // Path 2 (core): view latest version + view own unpublished content + is owner.
    $owner_access = AccessResult::allowedIfHasPermissions($account, ['view latest version', 'view own unpublished content']);
    $owner_access = $owner_access->andIf(AccessResult::allowedIf(
      $entity instanceof EntityOwnerInterface && $entity->getOwnerId() == $account->id()
    ));
    $access = $access->orIf($owner_access);
    if ($access->isAllowed()) {
      return $access->addCacheableDependency($entity);
    }

    // Path 3 (Mukurtu): protocol/language stewards for this node's protocols.
    if ($account->hasPermission('view latest version')
      && $entity instanceof NodeInterface
      && $entity instanceof CulturalProtocolControlledInterface
    ) {
      $node_protocols = $entity->getProtocols();
      if (!empty($node_protocols)) {
        foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
          if ($membership->getGroupEntityType() !== 'protocol') {
            continue;
          }
          $is_steward = $membership->hasRole('protocol-protocol-protocol_steward')
            || $membership->hasRole('protocol-protocol-language_steward');
          if ($is_steward && in_array((int) $membership->getGroupId(), array_map('intval', $node_protocols), TRUE)) {
            return AccessResult::allowed()
              ->cachePerUser()
              ->addCacheContexts(['og_role'])
              ->addCacheableDependency($entity);
          }
        }
      }
    }

    return $access->addCacheableDependency($entity);
  }

}

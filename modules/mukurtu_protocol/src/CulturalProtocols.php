<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;
use Drupal\user\Entity\User;

class CulturalProtocols {
  /**
   * Create a hash key for a protocol set.
   */
  public static function buildProtocolSetKey($protocols) {
    // Filter out any non IDs (nulls, whitespace).
    $filtered_protocols = array_filter($protocols, 'is_numeric');

    // Remove duplicates.
    $filtered_protocols = array_unique($filtered_protocols);

    // Sort so we don't have to worry about different combinations.
    sort($filtered_protocols);

    return implode(',', $filtered_protocols);
  }

  /**
   * Find an existing or create a new protocol set ID.
   *
   * Protocol sets are strings of protocol IDs in a specific order (see
   * buildProtocolSet). The ID is an integer representing a given set to make
   * resolving grants easier. For an example, an item with ALL(1,3) might have
   * a set of '1,3' which might resolve to ID 1. Any other item using ALL(1,3)
   * would resolve to protocol set ID 1 as well.
   */
  public static function getProtocolSetIdFromKey($key) {
    if (empty($key)) {
      return NULL;
    }

    $database = \Drupal::database();

    // Check if this set already has an ID.
    $query = $database->select('mukurtu_protocol_map', 'mpm')
    ->fields('mpm', ['protocol_set_id'])
    ->condition('protocol_set', $key)
      ->range(0, 1);
    $result = $query->execute()->fetch();

    // Return if it does.
    if ($result) {
      return $result->protocol_set_id;
    }

    // ID doesn't exist, insert it here and return new ID.
    $result = $database->insert('mukurtu_protocol_map')
    ->fields([
      'protocol_set' => $key,
    ])->execute();

    return $result;
  }

  public static function getProtocolSetId($protocols) {
    $key = self::buildProtocolSetKey($protocols);
    return self::getProtocolSetIdFromKey($key);
  }

  public static function getItemSharingSettingOptions() {
    return [
      'all' => t('All: This item may only be shared with members belonging to ALL the protocols listed.'),
      'any' => t('Any: This item may be shared with members of ANY protocol listed.'),
    ];
  }

  /**
   * Get the IDs of all published open protocols.
   */
  protected static function getAllOpenProtocols() {
    $query = \Drupal::entityQuery('protocol')
      ->condition('field_access_mode', 'open')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $results = $query->execute();

    return $results ? $results : [];
  }

  /**
   * Get a list of all compound protocols in use on the site.
   */
  protected static function getCompoundProtocols() {
    $compoundProtocols = [];
    $database = \Drupal::database();

    $query = $database->select('mukurtu_protocol_map', 'mpm')
    ->fields('mpm', ['protocol_set_id', 'protocol_set']);
    $result = $query->execute()->fetchAll();
    foreach ($result as $ps) {
      if (str_contains($ps->protocol_set, ',')) {
        $compoundProtocols[$ps->protocol_set_id] = explode(',', $ps->protocol_set);
      }
    }

    return $compoundProtocols;
  }

  public static function getAccountGrantIds(AccountInterface $account) {
    $grants = [];

    // Deny grant for missing protocols.
    $grants[0] = 0;

    /** @var \Drupal\og\OgMembershipInterface[] $memberships */
    $memberships = Og::getMemberships($account);
    $memberships = array_filter($memberships, fn ($e) => $e->getGroupEntityType() == 'protocol');

    // Get the protocol NID list and sort them.
    $protocols = array_map(fn ($e) => $e->getGroupId(), $memberships);
    sort($protocols);

    // User has access to all open protocols.
    foreach (self::getAllOpenProtocols() as $openProtocol) {
      $p_gid = self::getProtocolSetId([$openProtocol]);
      if (!in_array($openProtocol, $protocols)) {
        $protocols[] = $openProtocol;
      }
      $grants[$p_gid] = $p_gid;
    }

    // User has access to each single protocol they are a member of.
    foreach ($protocols as $protocol) {
      $p_gid = self::getProtocolSetId([$protocol]);
      $grants[$p_gid] = $p_gid;
    }

    // Search the entire protocol table for combinations of protocols
    // that the user is a member of. This is potentially slow, but it's faster
    // than computing the super set of user protocols.
    foreach (self::getCompoundProtocols() as $id => $setProtocols) {
      $inAll = TRUE;
      foreach ($setProtocols as $setProtocol) {
        if (!in_array($setProtocol, $protocols)) {
          $inAll = FALSE;
          break;
        }
      }
      if ($inAll) {
        $grants[$id] = $id;
      }
    }

    return $grants;
  }

  /**
   * Check if a user has a permission at the site or protocol level.
   *
   * @param EntityInterface $entity
   *   The entity under protocol. These are the protocols that will be queried.
   * @param string $permission
   *   The permission string.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   * @param boolean $force_require_all
   *   If true, the user will need the permission in all protocols regardless of
   *   privacy setting (any/all).
   *
   * @return boolean
   *   True if the user has the resultant permission. False otherwise.
   */
  public static function hasSiteOrProtocolPermission(EntityInterface $entity, $permission, AccountInterface $account, $force_require_all = FALSE) {
    // Site level has precedence.
    if ($account->hasPermission($permission)) {
      return TRUE;
    }

    // No protocols to check and the site permission was already checked.
    if (!($entity instanceof CulturalProtocolControlledInterface)) {
      return FALSE;
    }

    $protocols = $entity->getProtocolEntities();
    $sharing_setting = $entity->getSharingSetting();

    if (empty($protocols)) {
      return FALSE;
    }

    // Do we require agreement on all protocols or just one?
    if ($force_require_all || $sharing_setting === 'all') {
      $hasProtocolLevelPermission = TRUE;
      $and = TRUE;
    } else {
      $hasProtocolLevelPermission = FALSE;
      $and = FALSE;
    }
    foreach ($protocols as $protocol) {
      $membership = $protocol->getMembership($account);
      if ($and) {
        $hasProtocolLevelPermission = $hasProtocolLevelPermission && ($membership && $membership->hasPermission($permission));
      } else {
        $hasProtocolLevelPermission = $hasProtocolLevelPermission || ($membership && $membership->hasPermission($permission));
      }
    }

    return $hasProtocolLevelPermission;
  }

  public static function getProtocolsByUserPermission(array $permissions, $account = NULL) {
    if (!$account) {
      $account = \Drupal::currentUser()->getAccount();
    }

    // Look-up what OG roles can apply protocols.
    $ogRoleManager = \Drupal::service('og.role_manager');
    $roles = $ogRoleManager->getRolesByPermissions($permissions);
    $role_ids = array_keys($roles);

    // Check if the account has any of those roles.
    $memberships = Og::getMemberships($account);
    $protocols = [];
    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }

      foreach ($role_ids as $role_id) {
        if ($membership->hasRole($role_id)) {
          $protocols[$membership->getGroupId()] = $membership->getGroupId();
        }
      }
    }
    return $protocols;
  }

}

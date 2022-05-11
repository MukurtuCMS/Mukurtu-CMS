<?php

namespace Drupal\mukurtu_community_records\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\og\Og;

/**
 * Checks access for creating a community record.
 */
class AddCommunityRecordAccessCheck implements AccessInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * Construct an AddCommunityRecordAccessCheck object.
   *
   * @param \Drupal\Core\Routing\Access\AccountInterface $account
   *   The account to check access for.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(AccountInterface $account, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    return AccessResult::allowed();
    // Helper function to filter memberships to communities only.
    $communities_only = function ($e) {
      return $e->getGroupBundle() == 'community' ? TRUE : FALSE;
    };

    $protocols_only = function ($e) {
      return $e->getGroupBundle() == 'protocol' ? TRUE : FALSE;
    };

    // Find community memberships.
    $memberships = array_filter(Og::getMemberships($account), $communities_only);
    $protocol_memberships = array_filter(Og::getMemberships($account), $protocols_only);

    // User cannot create CRs if they have no community memberships.
    if (empty($memberships)) {
      return AccessResult::forbidden();
    }

    // Get the list of communities in which the user has the
    // administer community records permission.
    $has_cr_permission = function ($e) {
      return $e->hasPermission('administer community records');
    };
    $valid_cr_community_memberships = array_filter($memberships, $has_cr_permission);

    // User cannot create CRs if they have no community memberships in which
    // they have the administer community records permission.
    if (empty($valid_cr_community_memberships)) {
      return AccessResult::forbidden();
    }

    // Load the communities from the community memberships.
    $valid_cr_communities = [];
    foreach ($valid_cr_community_memberships as $valid_cr_community_membership) {
      $community = $valid_cr_community_membership->getGroup();
      $valid_cr_communities[$community->id()] = $community;
    }

    // We know at this point the user is in at least one community
    // where they have the administer community records permission.
    // Now we need to make sure they have permission to create
    // content.
    $fieldMap = $this->entityFieldManager->getFieldMap();

    // Theses are the node bundles that have the CR field.
    $validBundles = $fieldMap['node'][MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD]['bundles'] ?? [];
    if (empty($validBundles)) {
      return AccessResult::forbidden();
    }

    // Can the user create any of those types?
    foreach ($validBundles as $key => $bundle) {
      foreach ($protocol_memberships as $protocol_membership) {
        // Check if we have the permission in this protocol.
        if ($protocol_membership->hasPermission("create $bundle content")) {
          // Is this protocol contained in one of the allowed communities?
          $protocol = $protocol_membership->getGroup();
          $parent_communities = $protocol->get(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)->referencedEntities();
          foreach ($parent_communities as $parent_community) {
            if (isset($valid_cr_communities[$parent_community->id()])) {
              // As soon as we have one valid case we can return.
              return AccessResult::allowed();
            }
          }
        }
      }
    }

    return AccessResult::forbidden();
  }

}

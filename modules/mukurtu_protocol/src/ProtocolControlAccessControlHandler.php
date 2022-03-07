<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\og\Og;

/**
 * Access controller for the Protocol control entity.
 *
 * @see \Drupal\mukurtu_protocol\Entity\ProtocolControl.
 */
class ProtocolControlAccessControlHandler extends EntityAccessControlHandler {

  /**
   * The OG role manager.
   *
   * @var \Drupal\og\OgRoleManagerInterface
   */
  protected $ogRoleManager;
  //og.role_manager

  /**
   * Constructs an access control handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   */
  public function __construct(EntityTypeInterface $entity_type) {
    parent::__construct($entity_type);
    $this->ogRoleManager = \Drupal::getContainer()->get('og.role_manager');
  }

  /**
   * Get the protocols the account has permission to apply.
   */
  protected function getAccountApplyProtocols(AccountInterface $account) {
    // Look-up what OG roles can apply protocols.
    $roles = $this->ogRoleManager->getRolesByPermissions(['apply protocol']);
    $role_ids = array_keys($roles);

    // Check if the user has any of those roles.
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

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity */
    /** @var \Drupal\Core\Entity\EntityInterface $targetEntity */
    $targetEntity = $entity->getControlledEntity();
    $new = is_null($targetEntity);

    // For existing content we can deny users who
    // cannot see all involved protocols immediately
    // for any operation.
    if (!$new && !$entity->inAllGroups($account)) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished protocol control entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published protocol control entities');

      case 'update':
        if ($account->id() == 1) {
          return AccessResult::allowed();
        }

        // For new content, let anybody create.
        if ($new) {
          return AccessResult::allowedIfHasPermission($account, 'add protocol control entities');
        }

        // For existing content, user needs edit access to the
        // controlled entity and apply permission for each involved protocol.
        $protocols = $entity->getProtocols();
        $applyProtocols = $this->getAccountApplyProtocols($account);
        $diff = array_diff($protocols, $applyProtocols);
        if ($targetEntity->access('update', $account) && empty($diff)) {
          return AccessResult::allowedIfHasPermission($account, 'edit protocol control entities');
        }
        return AccessResult::forbidden();

      case 'delete':
        // Only the system gets to delete PCEs.
        return $account->id() == 1 ? AccessResult::allowed() : AccessResult::forbidden();

    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // User needs standard permission as well as at least one protocol
    // they can "apply".
    if (count($this->getAccountApplyProtocols($account)) > 0) {
      return AccessResult::allowedIfHasPermission($account, 'add protocol control entities');
    }
    return AccessResult::forbidden();
  }

}

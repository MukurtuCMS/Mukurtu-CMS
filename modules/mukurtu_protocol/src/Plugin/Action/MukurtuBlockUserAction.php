<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_core\Controller\MukurtuUserController;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use Drupal\views\ViewExecutable;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * VBO for blocking a user, scoped to community manager permissions.
 *
 * @Action(
 *   id = "mukurtu_block_user_action",
 *   label = @Translation("Block user"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuBlockUserAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof User) {
      return;
    }
    if (!$this->access($entity, \Drupal::currentUser())) {
      return;
    }
    if ($entity->status->value != 0) {
      $entity->set('status', FALSE);
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof User || !$account) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    if ($account->hasPermission('administer users')) {
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }

    // Community managers cannot block users with protected roles.
    if (array_intersect(MukurtuUserController::PROTECTED_ROLES, $object->getRoles())) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    // Community managers may only block users who share a managed community.
    $current_user = User::load($account->id());
    $cm_community_ids = [];
    foreach (Og::getMemberships($current_user) as $m) {
      if ($m->getGroupBundle() === 'community' && $m->hasPermission('manage members')) {
        $cm_community_ids[] = $m->getGroupId();
      }
    }

    $target_community_ids = [];
    foreach (Og::getMemberships($object) as $m) {
      if ($m->getGroupBundle() === 'community') {
        $target_community_ids[] = $m->getGroupId();
      }
    }

    $allowed = !empty(array_intersect($cm_community_ids, $target_community_ids));
    return $return_as_object ? ($allowed ? AccessResult::allowed() : AccessResult::forbidden()) : $allowed;
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool {
    $user = User::load($account->id());
    if (!$user) {
      return FALSE;
    }
    foreach (Og::getMemberships($user) as $m) {
      if ($m->getGroupBundle() === 'community' && $m->hasPermission('manage members')) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

class MukurtuManageContentController extends ControllerBase {

  /**
   * Check access for adding new community records.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = \Drupal::currentUser();
    if (!$account->isAnonymous()) {
      return AccessResult::allowedIfHasPermission($account, 'administer nodes');
    }

    return AccessResult::forbidden();
  }

  public function content() {
    $build = [];

    $view = [
      '#type' => 'view',
      '#name' => 'mukurtu_manage_all_content',
      '#display_id' => 'manage_all_content_block',
      '#embed' => TRUE,
    ];

    $build[] = $view;

    return $build;
  }
}

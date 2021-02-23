<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

class MukurtuManageMediaController extends ControllerBase {

  /**
   * Check access for adding new community records.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = \Drupal::currentUser();
    if (!$account->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  public function content() {
    $build = [];

    $view = [
      '#type' => 'view',
      '#name' => 'mukurtu_manage_all_media',
      '#display_id' => 'manage_all_media_block',
      '#embed' => TRUE,
    ];

    $build[] = $view;

    return $build;
  }
}

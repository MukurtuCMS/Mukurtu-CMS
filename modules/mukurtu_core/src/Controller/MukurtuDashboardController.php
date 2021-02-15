<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

class MukurtuDashboardController extends ControllerBase {

  /**
   * Check access for adding new community records.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    return AccessResult::allowed();
  }

  public function content() {
    $build = [];
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];

    // Display all recent content.
    $allRecentContentBlock = $block_manager->createInstance('views_block:mukurtu_recent_content-all_recent_content_block', $config);
    if ($allRecentContentBlock) {
      $access_result = $allRecentContentBlock->access(\Drupal::currentUser());
      if ($access_result == AccessResult::allowed()) {
        $build[] = $allRecentContentBlock->build();
      }
    }

    // Display all the user's recent content.
    $userRecentContentBlock = $block_manager->createInstance('views_block:mukurtu_recent_content-user_recent_content_block', $config);
    if ($userRecentContentBlock) {
      $access_result = $userRecentContentBlock->access(\Drupal::currentUser());
      if ($access_result == AccessResult::allowed()) {
        $build[] = $userRecentContentBlock->build();
      }
    }

    return $build;
  }
}

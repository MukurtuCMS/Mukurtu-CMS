<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class MukurtuDashboardController extends ControllerBase {

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
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];

    // Helper for community creation.
    $build[] = $this->gettingStartedCommunityContent();

    // Helper for category creation.
    $build[] = $this->gettingStartedCategoryContent();

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

  public function gettingStartedCommunityContent() {
    $build = [];

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'community')
      ->condition('status', TRUE);
    $results = $query->execute();

    if (count($results) == 0) {
      $entityManager = \Drupal::entityTypeManager();
      $accessControlHandler = $entityManager->getAccessControlHandler('node');
      if ($accessControlHandler->createAccess('community')) {
        $build[] = ['#markup' => '<div class="mukurtu-getting-started-communities">' . $this->t('Communities are a foundational component of Mukurtu CMS. Get started by creating your first community <a href="@create-community-page">here</a>.', ['@create-community-page' => Url::fromRoute('mukurtu_core.add', ['node_type' => 'community'])->toString()]) . '</div>'];
      }
    }

    return $build;
  }

  public function gettingStartedCategoryContent() {
    $build = [];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('category');

    if (count($terms) == 1 && $terms[0]->name == 'Default') {
      $build[] = ['#markup' => '<div class="mukurtu-getting-started-categories">' . $this->t('Categories are important for grouping related content. Consider adding new terms and removing the default term <a href="@manage-category-page">here</a>.', ['@manage-category-page' => Url::fromRoute('mukurtu_taxonomy.manage_categories')->toString()]) . '</div>'];
    }

    return $build;
  }

}

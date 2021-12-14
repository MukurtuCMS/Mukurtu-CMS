<?php

namespace Drupal\mukurtu_rights\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class MukurtuManageCommunityHubProjectController extends ControllerBase {

  /**
   * Check access for managing a community's local context hub project.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The community.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() == 'community') {
      return $node->access('update', $account, TRUE);
    }

    return AccessResult::forbidden();
  }

  /**
   * Manage community hub project page.
   */
  public function content(NodeInterface $node) {
    $build = [];
    $build['project'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_rights\Form\LocalContextsHubCommunitySettingsForm', $node);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(NodeInterface $node) {
    return $this->t("Manage Local Contexts Hub project for %node", ['%node' => $node->getTitle()]);
  }

}

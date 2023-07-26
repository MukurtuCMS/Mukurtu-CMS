<?php

namespace Drupal\mukurtu_local_contexts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns responses for Local Contexts routes.
 */
class ManageGroupSupportedProjectsController extends ControllerBase {


  /**
   * Checks access for manage group projects form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, ContentEntityInterface $group = NULL) {
    if ($group) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  public function title(ContentEntityInterface $group = NULL) {
    return $this->t("Manage Local Contexts Projects for %group", ['%group' => $group ? $group->getName() : 'Unknown Group']);
  }

}

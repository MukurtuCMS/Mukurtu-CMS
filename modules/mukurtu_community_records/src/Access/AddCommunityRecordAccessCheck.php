<?php

namespace Drupal\example\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Checks access for creating a community record.
 */
class AddCommunityRecordAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
/*   public function access(AccountInterface $account) {
    //\Drupal::logger('mukurtu_community_records')->notice("I am here");
    dpm($account);
    return (TRUE) ? AccessResult::allowed() : AccessResult::forbidden();
  } */
}

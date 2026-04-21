<?php

namespace Drupal\term_merge\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Verifies that a given user is allowed to merge terms.
 */
class MergeAccess implements AccessInterface {

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $taxonomy_vocabulary
   *   Run access checks against this vocabulary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Vocabulary $taxonomy_vocabulary, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'edit terms in ' . $taxonomy_vocabulary->id())->orIf(
      AccessResult::allowedIfHasPermission($account, 'administer taxonomy'));
  }

}

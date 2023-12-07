<?php

namespace Drupal\mukurtu_migrate;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for mukurtu_migrate routes.
 *
 * The Mukurtu migration can only be used by user 1. This is because any other
 * user might have different permissions on the source and target site.
 *
 * In addition, migration can only occur if there is no pre-existing site
 * content. Since uids are preserved during migration, we do not want cases
 * where existing content is overwritten in strange ways.
 *
 * This class is designed to be used with '_custom_access' route requirement.
 *
 * @see \Drupal\Core\Access\CustomAccessCheck
 */
class MukurtuMigrateAccessCheck {

  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkOverviewAccess(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    return AccessResultAllowed::allowedIf((int) $account->id() === 1)->mergeCacheMaxAge(0);
  }

  /**
   * Checks if the user is user 1 and that there is no existing site content.
   * Grants access if these conditions are met.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(AccountInterface $account)
  {
    if ($this->siteHasContent()) {
      return AccessResult::forbidden();
    }
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    return AccessResultAllowed::allowedIf((int) $account->id() === 1)->mergeCacheMaxAge(0);
  }

  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccessResults(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    return AccessResultAllowed::allowedIf((int) $account->id() === 1)->mergeCacheMaxAge(0);
  }

  /**
   * Checks if there are existing entities on the site prior to migration.
   * The content types were decided here:
   * https://github.com/MukurtuCMS/Mukurtu-CMS/issues/135.
   *
   * @return bool
   * TRUE if site has content, FALSE if not.
   */
  protected function siteHasContent() {
    $contentTypes = ['node', 'media', 'community', 'protocol'];
    foreach ($contentTypes as $contentType) {
      $query = \Drupal::entityQuery($contentType)
        ->range(0, 1)
        ->accessCheck(FALSE);
      $result = $query->execute();
      if ($result) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

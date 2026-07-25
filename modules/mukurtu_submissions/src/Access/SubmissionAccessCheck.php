<?php

namespace Drupal\mukurtu_submissions\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check for the public submission form route.
 */
class SubmissionAccessCheck implements AccessInterface {

  /**
   * Checks access for /submit/{entity_type_id}/{bundle}.
   */
  public function access(AccountInterface $account, $entity_type_id, $bundle): AccessResultInterface {
    $entity_type_manager = \Drupal::entityTypeManager();
    $settings = $entity_type_manager->getStorage('mukurtu_submission_settings')->loadByProperties([
      'target_entity_type_id' => $entity_type_id,
      'target_bundle' => $bundle,
    ]);
    $setting = reset($settings);

    if (!$setting || !$setting->status()) {
      return AccessResult::forbidden('Public submissions are not enabled for this content type.');
    }

    return AccessResult::allowedIfHasPermission($account, "submit $entity_type_id $bundle content")
      ->addCacheableDependency($setting);
  }

}

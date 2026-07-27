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

    // The public form only ever renders whichever fields the target
    // bundle's "submission" form display explicitly exposes. Without that
    // display saved as config, EntityDisplayRepository::getFormDisplay()
    // falls back to the bundle's default add-form display, which could
    // expose fields never meant to be public - deny access rather than
    // risk that, even if a reviewer has otherwise enabled this bundle.
    $form_display_config = \Drupal::config("core.entity_form_display.$entity_type_id.$bundle.submission");
    if (!$form_display_config->get('id')) {
      // Add the (currently empty) config as a cacheable dependency too, not
      // just $setting - Drupal tags a config object's cache tag with its own
      // name even before it exists, so this 403 gets invalidated the moment
      // that display is created, rather than lingering until caches expire.
      return AccessResult::forbidden('This content type has no "submission" form display configured.')
        ->addCacheableDependency($setting)
        ->addCacheableDependency($form_display_config);
    }

    return AccessResult::allowedIfHasPermission($account, "submit $entity_type_id $bundle content")
      ->addCacheableDependency($setting);
  }

}

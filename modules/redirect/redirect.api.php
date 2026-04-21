<?php

/**
 * @file
 * Hooks provided by the Redirect module.
 */

use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * @defgroup redirect_api_hooks Redirect API Hooks
 * @{
 * During redirect operations (create, update, view, delete, etc.), there are
 * several sets of hooks that get invoked to allow modules to modify the
 * redirect operation:
 * - All-module hooks: Generic hooks for "redirect" operations. These are
 *   always invoked on all modules.
 * - Entity hooks: Generic hooks for "entity" operations. These are always
 *   invoked on all modules.
 *
 * Here is a list of the redirect and entity hooks that are invoked, and other
 * steps that take place during redirect operations:
 * - Creating a new redirect (calling redirect_save() on a new redirect):
 *   - hook_redirect_presave() (all)
 *   - Redirect written to the database
 *   - hook_redirect_insert() (all)
 *   - hook_entity_insert() (all)
 * - Updating an existing redirect (calling redirect_save() on an existing redirect):
 *   - hook_redirect_presave() (all)
 *   - Redirect written to the database
 *   - hook_redirect_update() (all)
 *   - hook_entity_update() (all)
 * - Loading a redirect (calling redirect_load(), redirect_load_multiple(), or
 *   entity_load() with $entity_type of 'redirect'):
 *   - Redirect information is read from database.
 *   - hook_entity_load() (all)
 *   - hook_redirect_load() (all)
 * - Deleting a redirect (calling redirect_delete() or redirect_delete_multiple()):
 *   - Redirect is loaded (see Loading section above)
 *   - Redirect information is deleted from database
 *   - hook_redirect_delete() (all)
 *   - hook_entity_delete() (all)
 * - Preparing a redirect for editing (note that if it's
 *   an existing redirect, it will already be loaded; see the Loading section
 *   above):
 *   - hook_redirect_prepare_form() (all)
 * - Validating a redirect during editing form submit (calling
 *   redirect_form_validate()):
 *   - hook_redirect_validate() (all)
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act on a redirect response when it is triggered.
 *
 * This hook is invoked before the response is sent to the user. The redirect
 * entity itself is sent as well for inspection.
 *
 * @param Drupal\Core\Routing\TrustedRedirectResponse $response
 *   The generated redirect response object before it is delivered.
 * @param \Drupal\redirect\Entity\Redirect $redirect
 *   The redirect entity used to generate the response object.
 *
 * @ingroup redirect_api_hooks
 */
function hook_redirect_response_alter(TrustedRedirectResponse $response, \Drupal\redirect\Entity\Redirect $redirect) {
  // Set a drupal message.
  if (!$redirect->getRedirectUrl()->isExternal()) {
    \Drupal::messenger()->addWarning(t('You are not being directed off-site.'));
  }

  // If `some condition`, send to Drupal.org.
  if (FALSE) {
    $response->setTrustedTargetUrl('http://drupal.org');
  }
}

/**
 * @} End of "addtogroup hooks".
 */

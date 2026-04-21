<?php

/**
 * @file
 * Hooks for gin theme.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Register routes to apply Gin’s content edit form layout.
 *
 * Leverage this hook to achieve a consistent user interface layout on
 * administrative edit forms, similar to the node edit forms. Any module
 * providing a custom entity type or form mode may wish to implement this
 * hook for their form routes. Please note that not every content entity
 * form route should enable the Gin edit form layout, for example the
 * delete entity form does not need it.
 *
 * @return array
 *   An array of route names.
 *
 * @see GinContentFormHelper->isContentForm()
 * @see hook_gin_content_form_routes_alter()
 */
function hook_gin_content_form_routes() {
  return [
    // Layout a custom node form.
    'entity.node.my_custom_form',

    // Layout a custom entity type edit form.
    'entity.my_type.edit_form',
  ];
}

/**
 * Alter the registered routes to enable or disable Gin’s edit form layout.
 *
 * @param array $routes
 *   The list of routes.
 *
 * @see GinContentFormHelper->isContentForm()
 * @see hook_gin_content_form_routes()
 */
function hook_gin_content_form_routes_alter(array &$routes) {
  // Example: disable Gin edit form layout customizations for an entity type.
  $routes = array_diff($routes, ['entity.my_type.edit_form']);
}

/**
 * Register form ids to opt-out of Gin’s sticky action buttons.
 *
 * Leverage this hook to opt-out of Gin's sticky action buttons.
 * Opting out will keep the action buttons within the form.
 *
 * @return array
 *   An array of form ids to ignore.
 *
 * @see GinContentFormHelper->stickyActionButtons()
 */
function hook_gin_ignore_sticky_form_actions() {
  return [
    // My custom form.
    'my_custom_form',
  ];
}

/**
 * @} End of "addtogroup hooks".
 */

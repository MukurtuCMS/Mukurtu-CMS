<?php

/**
 * @file
 * Hooks related to entity browser and it's plugins.
 */

use Drupal\views\ViewExecutable;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\EntityBrowserDisplay.
 *
 * @param array $displays
 *   The array of display plugins, keyed on the machine-readable name.
 */
function hook_entity_browser_display_info_alter(array &$displays) {
  $displays['modal_display']['label'] = t('Superb fancy stuff!');
}

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\EntityBrowserWidget.
 *
 * @param array $widgets
 *   The array of widget plugins, keyed on the machine-readable name.
 */
function hook_entity_browser_widget_info_alter(array &$widgets) {
  $widgets['view_widget']['label'] = t('Views FTW!');
}

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\SelectionDisplay.
 *
 * @param array $selection_displays
 *   The array of selection display plugins, keyed on the machine-readable name.
 */
function hook_entity_browser_selection_display_info_alter(array &$selection_displays) {
  $selection_displays['no_selection']['label'] = t('Nothing!');
}

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\EntityBrowserWidgetSelector.
 *
 * @param array $widget_selectors
 *   The array of widget selector plugins, keyed on the machine-readable name.
 */
function hook_entity_browser_widget_selector_info_alter(array &$widget_selectors) {
  $widgets['tab_selector']['label'] = t('Tabs are for winners');
}

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\EntityBrowserFieldWidgetDisplay.
 *
 * @param array $field_displays
 *   The array of field widget display plugins, keyed on the machine-readable
 *   name.
 */
function hook_entity_browser_field_widget_display_info_alter(array &$field_displays) {
  $field_displays['rendered_entity']['label'] = t('Entity render system FTW');
}

/**
 * Alter the information provided in \Drupal\entity_browser\Annotation\EntityBrowserWidgetValidation.
 *
 * @param array $validation_plugins
 *   The array of widget validation plugins, keyed on the machine-readable
 *   name.
 */
function hook_entity_browser_widget_validation_info_alter(array &$validation_plugins) {
  $field_displays['not_null']['label'] = t('Not null fabulous validator');
}

/**
 * Edit any entity browser form.
 *
 * Implements hook_form_BASE_FORM_ID_alter().
 */
function hook_form_entity_browser_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $form['#attributes']['class'][] = 'hello-world';
}

/**
 * Edit specific entity browser form.
 *
 * Implements hook_form_FORM_ID_alter().
 */
function hook_form_entity_browser_ENTITY_BROWSER_ID_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $form['#attributes']['class'][] = 'hello-world';
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * Alter the view in the view widget before rendering.
 *
 * @param \Drupal\views\ViewExecutable $view
 *   The view executable.
 * @param array $configuration
 *   The entity browser view widget configuration.
 * @param array $context
 *   Helpful contextual information.
 *
 * @see \Drupal\entity_browser\Plugin\EntityBrowser\Widget\View::getContext()
 */
function hook_entity_browser_view_executable_alter(ViewExecutable &$view, array &$configuration, array $context) {
  if ($context['original_route_match']->getParameter('node') && $view->id() == 'my_entity_browser_view') {
    $original_node = $context['original_route_match']->getParameter('node');
    $view->args = [$original_node->id()];
  }
}

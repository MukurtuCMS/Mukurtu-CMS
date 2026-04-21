<?php

/**
 * @file
 * Hooks provided by the geolocation module.
 */

/**
 * Alter the map based widgets.
 *
 * @param array $element
 *   Element.
 * @param mixed $context
 *   Context.
 *
 * @code
 *   $context = [
 *     'widget' => \Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationMapWidgetBase
 *     'form_state' => \Drupal\Core\Form\FormStateInterface $form_state
 *     'field_definition' => \Drupal\Core\Field\FieldDefinitionInterface $field_definition
 *   ];
 * @endcode
 *
 * @see \Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationMapWidgetBase
 */
function hook_geolocation_field_map_widget_alter(array &$element, $context) {
  // Do something.
}

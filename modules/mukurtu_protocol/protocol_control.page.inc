<?php

/**
 * @file
 * Contains protocol_control.page.inc.
 *
 * Page callback for Protocol control entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Protocol control templates.
 *
 * Default template: protocol_control.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_protocol_control(array &$variables) {
  // Fetch ProtocolControl Entity Object.
  $protocol_control = $variables['elements']['#protocol_control'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

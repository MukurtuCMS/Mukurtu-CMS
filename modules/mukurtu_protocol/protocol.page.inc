<?php

/**
 * @file
 * Contains protocol.page.inc.
 *
 * Page callback for Protocol entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Protocol templates.
 *
 * Default template: protocol.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_protocol(array &$variables) {
  // Fetch Protocol Entity Object.
  $protocol = $variables['elements']['#protocol'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

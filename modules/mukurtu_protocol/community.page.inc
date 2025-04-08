<?php

/**
 * @file
 * Contains community.page.inc.
 *
 * Page callback for Community entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Community templates.
 *
 * Default template: community.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_community(array &$variables): void {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

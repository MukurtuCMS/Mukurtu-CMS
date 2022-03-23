<?php

/**
 * @file
 * Contains personal_collection.page.inc.
 *
 * Page callback for Personal collection entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Personal collection templates.
 *
 * Default template: personal_collection.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_personal_collection(array &$variables) {
  // Fetch PersonalCollection Entity Object.
  $personal_collection = $variables['elements']['#personal_collection'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

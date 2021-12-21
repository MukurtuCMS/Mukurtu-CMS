<?php

/**
 * @file
 * Contains language_community.page.inc.
 *
 * Page callback for Language community entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Language community templates.
 *
 * Default template: language_community.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_language_community(array &$variables) {
  // Fetch LanguageCommunity Entity Object.
  $language_community = $variables['elements']['#language_community'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

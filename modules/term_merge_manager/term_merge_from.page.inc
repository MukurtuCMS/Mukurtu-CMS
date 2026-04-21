<?php

/**
 * @file
 * Contains term_merge_from.page.inc.
 *
 * Page callback for Term merge from entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Term merge from templates.
 *
 * Default template: term_merge_from.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_term_merge_from(array &$variables) {
  // Fetch TermMergeFrom Entity Object.
  // $term_merge_from = $variables['elements']['#term_merge_from'];.
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

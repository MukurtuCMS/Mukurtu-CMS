<?php

/**
 * @file
 * Contains term_merge_into.page.inc.
 *
 * Page callback for Term merge into entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Term merge into templates.
 *
 * Default template: term_merge_into.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_term_merge_into(array &$variables) {
  // Fetch TermMergeInto Entity Object.
  // $term_merge_into = $variables['elements']['#term_merge_into'];.
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

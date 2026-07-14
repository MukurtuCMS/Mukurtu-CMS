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

  // The Twig sandbox blocks calling methods like toUrl() and label()
  // directly on custom entities, so precompute what the templates need.
  $variables['url'] = $personal_collection->toUrl()->toString();
  $variables['label'] = $personal_collection->label();
  $variables['is_private'] = $personal_collection->isPrivate();
  $variables['item_count'] = $personal_collection->getCount();

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

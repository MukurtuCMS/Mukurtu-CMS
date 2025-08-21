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
  // Format members display box based on community membership display setting.
  if (isset($variables['elements']['#community']) && isset($variables['elements']['field_members_list'])) {
    $community = $variables['elements']['#community'];
    switch ($community->getMembershipDisplay()) {
      case 'managers':
        $variables['elements']['field_members_list']['#title'] = t("Community managers");
        break;
      case 'all':
        $variables['elements']['field_members_list']['#title'] = t("All members");
        break;
    }
  }
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

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
function template_preprocess_protocol(array &$variables): void {
  // Format members display box based on protocol's membership display setting.
  if (isset($variables['elements']['#protocol']) && isset($variables['elements']['field_members_list'])) {
    $protocol = $variables['elements']['#protocol'];
    switch ($protocol->getMembershipDisplay()) {
      case 'stewards':
        $variables['elements']['field_members_list']['#title'] = t("Protocol stewards");
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

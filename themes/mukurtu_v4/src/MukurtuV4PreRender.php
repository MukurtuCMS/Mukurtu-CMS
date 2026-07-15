<?php

namespace Drupal\mukurtu_v4;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements trusted prerender callbacks for the Mukurtu CMS 4 theme.
 *
 * @internal
 */
class MukurtuV4PreRender implements TrustedCallbackInterface {

  /**
   * Prerender callback for status_messages placeholder.
   *
   * Core's default fallback markup lacks the `messages-list` class our
   * status-messages.html.twig and message.theme.js both rely on for layout,
   * so if no [data-drupal-messages] placeholder exists at initial page load
   * and JS falls back to this element (see core/misc/message.js
   * Drupal.Message.defaultWrapper()), the un-hidden fallback renders without
   * any of our message styling. Olivero and Claro patch this the same way
   * for their own themes; mukurtu_v4 needs the same patch since it isn't
   * based on either.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array containing the placeholder.
   */
  public static function messagePlaceholder(array $element) {
    if (isset($element['fallback']['#markup'])) {
      $element['fallback']['#markup'] = '<div data-drupal-messages-fallback class="hidden messages-list"></div>';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'messagePlaceholder',
    ];
  }

}

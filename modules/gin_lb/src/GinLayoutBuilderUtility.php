<?php

declare(strict_types=1);

namespace Drupal\gin_lb;

use Drupal\Core\Render\Element;

/**
 * Contains helper methods for Gin Layout Builder.
 */
class GinLayoutBuilderUtility {

  /**
   * Attaches gin_lb_form to all form elements.
   *
   * @param array $form
   *   The form or form element which children should have form id attached.
   */
  public static function attachGinLbForm(array &$form): void {
    foreach (Element::children($form) as $child) {
      if (!isset($form[$child]['#gin_lb_form'])) {
        $form[$child]['#gin_lb_form'] = TRUE;
      }
      static::attachGinLbForm($form[$child]);
    }
  }

}

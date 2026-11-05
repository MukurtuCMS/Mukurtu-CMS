/**
 * @file
 * Keeps focus on the Paragraphs widget's Collapse / Expand buttons.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Focuses the button that replaced the one just clicked.
   *
   * Collapse becomes Expand (and Collapse all becomes Expand all, and back)
   * through an AJAX rebuild that destroys the clicked button, dropping focus
   * to the page. ParagraphsWidgetHooks marks the replacement.
   */
  Drupal.behaviors.mukurtuParagraphsWidgetFocus = {
    attach: function (context) {
      once(
        'mukurtu-paragraphs-focus',
        '[data-mukurtu-paragraphs-focus]',
        context
      ).forEach(function (button) {
        button.removeAttribute('data-mukurtu-paragraphs-focus');
        button.focus();
      });
    }
  };

})(Drupal, once);

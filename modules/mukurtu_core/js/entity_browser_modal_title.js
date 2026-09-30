/**
 * @file
 * Names the iframe inside an entity browser modal (WCAG 4.1.2).
 *
 * entity_browser renders the iframe without a title, so screen readers
 * announce it with no name. Give it the dialog's own title, the same text
 * the dialog shows ("Select Content", "Select communities", and so on).
 */

(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuEntityBrowserModalTitle = {
    attach: function () {
      once('eb-modal-title', 'html').forEach(function () {
        $(window).on('dialog:aftercreate', function (event, dialog, $element) {
          var $iframe = $element.find('iframe.entity-browser-modal-iframe');
          if (!$iframe.length || $iframe.attr('title')) {
            return;
          }
          // The rendered title bar's text, not the raw option: the option may
          // be markup, and parsing it would be an injection risk.
          var title = $element.closest('.ui-dialog').find('.ui-dialog-title').first().text().trim();
          if (title) {
            $iframe.attr('title', title);
          }
        });
      });
    }
  };

}(jQuery, Drupal, once));

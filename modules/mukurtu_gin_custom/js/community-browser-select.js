/**
 * @file
 * Makes rows in the community select browser selectable by clicking the card.
 *
 * Hides the entity_browser_select checkbox and proxies row clicks to it,
 * adding a visual selected state. The user still clicks "Add communities"
 * to confirm the selection.
 */
(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuCommunityBrowserSelect = {
    attach: function (context) {
      once('community-browser-select', '.view-mukurtu-community-select', context).forEach(function (view) {
        var $view = $(view);

        // Make each row focusable and give it a checkbox role so keyboard users
        // and screen readers can interact with it (WCAG 2.1.1, 4.1.2).
        // Remove the underlying checkbox from the tab order and accessibility
        // tree — the .views-row is the sole interactive element for AT.
        $view.find('.views-row').each(function () {
          $(this).attr({ tabindex: '0', role: 'checkbox', 'aria-checked': 'false' });
          $(this).find('.views-field-entity-browser-select input')
            .attr({ tabindex: '-1', 'aria-hidden': 'true' });
        });

        // Handle both click and keyboard (Enter/Space) to toggle selection.
        $view.on('click keydown', '.views-row', function (e) {
          if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
          }
          if (e.type === 'keydown') {
            e.preventDefault();
          }
          if ($(e.target).is('input')) {
            return;
          }
          var $checkbox = $(this).find('.views-field-entity-browser-select input');
          if (!$checkbox.length) {
            return;
          }
          var checked = !$checkbox.prop('checked');
          $checkbox.prop('checked', checked);
          $(this).toggleClass('is-selected', checked).attr('aria-checked', String(checked));
        });
      });

      // Runs in the parent document only. The entity browser renders content
      // inside an <iframe>. jQuery UI's focus trap uses :tabbable, which never
      // includes <iframe> elements, so Tab from the dialog close button cycles
      // endlessly on the close button — keyboard events stay in the parent
      // window even after programmatic focus is set inside the iframe.
      //
      // Fix: intercept Tab on the close button and call
      // iframe.contentWindow.focus() before focusing the first row. Calling
      // contentWindow.focus() during a user-initiated keydown event transfers
      // keyboard event dispatch to the iframe's browsing context, so
      // subsequent Tab presses cycle through the community rows as expected.
      if (window.self === window.top) {
        once('community-browser-focus', 'body', context).forEach(function () {
          $(window).on('dialog:aftercreate', function (event, dialog, $element) {
            var $iframe = $element.find('.entity-browser-modal-iframe');
            if (!$iframe.length) { return; }
            var iframe = $iframe[0];

            var $closeButton = $element.closest('.ui-dialog').find('.ui-dialog-titlebar-close');

            $closeButton.on('keydown.eb-community', function (e) {
              if (e.key !== 'Tab' || e.shiftKey) { return; }
              e.preventDefault();
              e.stopImmediatePropagation();

              var doc = iframe.contentDocument;
              if (!doc) { iframe.focus(); return; }
              var firstRow = doc.querySelector('.view-mukurtu-community-select .views-row');
              if (!firstRow) { iframe.focus(); return; }
              iframe.contentWindow.focus();
              firstRow.focus();
            });

            // Clean up the close-button listener when the dialog closes.
            $element.on('dialogclose.eb-community', function () {
              $closeButton.off('.eb-community');
            });
          });
        });
      }
    }
  };

}(jQuery, Drupal, once));

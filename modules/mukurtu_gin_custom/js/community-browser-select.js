/**
 * @file
 * Makes community cards in the community select browser clickable.
 *
 * Each card's native checkbox is the control, named by its "Select item
 * <community>" label. Clicking elsewhere on the card toggles it too, and the
 * card shows a selected state. The user still clicks "Add communities" to
 * confirm the selection.
 */
(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuCommunityBrowserSelect = {
    attach: function (context) {
      once('community-browser-select', '.view-mukurtu-community-select', context).forEach(function (view) {
        var $view = $(view);

        $view.find('.views-row').each(function () {
          var $row = $(this);
          var $input = $row.find('.views-field-entity-browser-select input');
          $row.toggleClass('is-selected', $input.prop('checked'));
          $input.on('change', function () {
            $row.toggleClass('is-selected', $input.prop('checked'));
          });
        });

        $view.on('click', '.views-row', function (e) {
          // Leave the input and its label to the browser.
          if ($(e.target).closest('input, label').length) {
            return;
          }
          var $checkbox = $(this).find('.views-field-entity-browser-select input');
          if (!$checkbox.length || $checkbox.prop('disabled')) {
            return;
          }
          $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        });
      });

      // Runs in the parent document only. The entity browser renders content
      // inside an <iframe>. jQuery UI's focus trap uses :tabbable, which never
      // includes <iframe> elements, so Tab from the dialog close button cycles
      // endlessly on the close button — keyboard events stay in the parent
      // window even after programmatic focus is set inside the iframe.
      //
      // Fix: intercept Tab on the close button and call
      // iframe.contentWindow.focus() before focusing the first checkbox. Calling
      // contentWindow.focus() during a user-initiated keydown event transfers
      // keyboard event dispatch to the iframe's browsing context, so
      // subsequent Tab presses cycle through the community checkboxes.
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
              var first = doc.querySelector('.view-mukurtu-community-select .views-field-entity-browser-select input:enabled');
              if (!first) { iframe.focus(); return; }
              iframe.contentWindow.focus();
              first.focus();
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

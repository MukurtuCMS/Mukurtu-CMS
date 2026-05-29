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

      // Runs in the top-level document only. jQuery UI's focus trap uses its
      // own :tabbable selector which does not recognise <iframe> elements, so
      // Tab from the dialog's close button cycles back to the close button and
      // never enters the iframe. We bridge the gap by moving focus to the first
      // community row once the iframe finishes loading.
      if (window.self === window.top) {
        once('community-browser-focus', 'body', context).forEach(function () {
          $(document).on('dialogopen', '.ui-dialog', function () {
            var iframe = $(this).find('.entity-browser-modal-iframe')[0];
            if (!iframe) { return; }

            iframe.addEventListener('load', function () {
              var iframeDoc = this.contentDocument;
              if (!iframeDoc) { return; }
              var firstRow = iframeDoc.querySelector('.view-mukurtu-community-select .views-row');
              if (firstRow) {
                firstRow.focus({ preventScroll: true });
              }
            });
          });
        });
      }
    }
  };

}(jQuery, Drupal, once));

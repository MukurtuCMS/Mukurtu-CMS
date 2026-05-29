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

      // Runs inside the entity browser iframe only. jQuery UI's focus trap in
      // the parent dialog uses :tabbable, which doesn't include <iframe>
      // elements, so Tab from the close button can never reach rows in the
      // iframe. Moving focus from within the iframe sidesteps the race
      // condition that would occur if we tried to do this from the parent
      // after the iframe's load event (which fires almost immediately on
      // localhost before a parent-side listener can be added).
      if (window.self !== window.top) {
        once('community-browser-autofocus', 'body', context).forEach(function () {
          var firstRow = document.querySelector('.view-mukurtu-community-select .views-row');
          if (firstRow) {
            firstRow.focus({ preventScroll: true });
          }
        });
      }
    }
  };

}(jQuery, Drupal, once));

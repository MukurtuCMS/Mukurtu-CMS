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
        $view.find('.views-row').each(function () {
          $(this).attr({ tabindex: '0', role: 'checkbox', 'aria-checked': 'false' });
        });

        // Handle both click and keyboard (Enter/Space) to toggle selection.
        // The underlying checkbox is kept in the accessibility tree via
        // visually-hidden CSS (aria-hidden is NOT set on it), but the .views-row
        // is the primary interactive element exposed to assistive technology.
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
    }
  };

}(jQuery, Drupal, once));

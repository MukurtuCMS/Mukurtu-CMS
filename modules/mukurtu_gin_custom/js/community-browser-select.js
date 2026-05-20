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

        // Make each row a clickable card that toggles selection.
        // Note: .views-field-entity-browser-select is hidden via CSS to avoid
        // a flash before this JS runs.
        $view.on('click', '.views-row', function (e) {
          if ($(e.target).is('input')) {
            return;
          }
          var $checkbox = $(this).find('.views-field-entity-browser-select input');
          if (!$checkbox.length) {
            return;
          }
          var checked = !$checkbox.prop('checked');
          $checkbox.prop('checked', checked);
          $(this).toggleClass('is-selected', checked);
        });
      });
    }
  };

}(jQuery, Drupal, once));

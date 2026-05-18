/**
 * @file protocol-community-browser.js
 *
 * Auto-triggers member list refresh when the community entity browser
 * selection is completed.
 */

(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.mukurtuProtocolCommunityBrowser = {
    attach: function (context) {
      once('mukurtu-community-auto-update', '#communities-and-members-wrapper', context)
        .forEach(function (wrapper) {
          $(wrapper).on('entity_browser_value_updated', function () {
            $(wrapper).find('.js-communities-auto-update').trigger('click');
          });
        });
    }
  };

}(jQuery, Drupal, once));

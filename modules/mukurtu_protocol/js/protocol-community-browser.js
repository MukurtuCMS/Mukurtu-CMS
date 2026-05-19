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
      // querySelectorAll only searches descendants, not the element itself.
      // After AJAX, context IS the wrapper, so we must handle both cases.
      const candidates = (context instanceof Element && context.id === 'communities-and-members-wrapper')
        ? [context]
        : Array.from(context.querySelectorAll('#communities-and-members-wrapper'));

      once('mukurtu-community-auto-update', candidates).forEach(function (wrapper) {
        $(wrapper).on('entity_browser_value_updated', function () {
          $(wrapper).find('.js-communities-auto-update').trigger('click');
        });
      });
    }
  };

}(jQuery, Drupal, once));

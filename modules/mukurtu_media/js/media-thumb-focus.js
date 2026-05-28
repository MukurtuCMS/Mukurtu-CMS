/**
 * @file
 * Prevents the media library modal from jumping to the top when a thumbnail
 * is uploaded. Saves the modal scroll position before the AJAX fires and
 * scrolls the thumbnail wrapper back into view after the response lands.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      once('mukurtu-thumb-focus', '#media-library-wrapper', context).forEach(function (wrapper) {
        wrapper.addEventListener('mousedown', function (e) {
          var btn = e.target.closest('[id*="field-thumbnail"][id$="-upload-button"]');
          if (!btn) return;

          var scrollable = wrapper.closest('.ui-dialog-content');
          if (!scrollable) return;

          $(document).one('ajaxSuccess.thumbFocus', function () {
            var thumbWrapper = wrapper.querySelector('[id*="field-thumbnail-wrapper"]');
            if (!thumbWrapper) return;
            var wrapperRect = thumbWrapper.getBoundingClientRect();
            var modalRect = scrollable.getBoundingClientRect();
            if (wrapperRect.top < modalRect.top || wrapperRect.bottom > modalRect.bottom) {
              scrollable.scrollTop += (wrapperRect.top - modalRect.top) - 20;
            }
          });
        }, true);
      });
    }
  };

}(jQuery, Drupal, once));

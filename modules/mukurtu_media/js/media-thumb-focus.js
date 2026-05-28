/**
 * @file
 * Prevents the media library modal from jumping to the top after a thumbnail
 * is uploaded in the external embed add form.
 *
 * Root cause: after an upload AJAX completes, Drupal walks the triggering
 * element's ancestors and calls .trigger('focus') on the first one still in
 * the DOM. That focus call scrolls the .ui-dialog-content container to the
 * top. The PHP form alter sets disable_refocus on the upload button as the
 * primary fix; this JS is a reliable belt-and-suspenders layer.
 *
 * Key timing notes from core/misc/ajax.js:
 *  - ajaxSuccess / ajaxComplete are delayed until after the entire command
 *    queue (including the refocus logic) resolves. So ajaxComplete fires
 *    *after* any scroll caused by refocus — making it the right hook to
 *    restore position.
 *  - The upload button is js-hide and clicked programmatically; the user
 *    interacts with the <input type="file"> instead. Its 'change' event is
 *    the correct trigger to capture the pre-upload scroll position.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      // Capture scroll position when a file is chosen via the thumbnail input,
      // then restore it after ajaxComplete (post-refocus, post-behaviors).
      once('mukurtu-thumb-file', '[id*="field-thumbnail"] input[type="file"]', context).forEach(function (input) {
        var scrollable = input.closest('.ui-dialog-content');
        if (!scrollable) return;

        input.addEventListener('change', function () {
          var savedScroll = scrollable.scrollTop;
          $(document).one('ajaxComplete.thumbFocus', function () {
            scrollable.scrollTop = savedScroll;
          });
        });
      });
    }
  };

}(jQuery, Drupal, once));

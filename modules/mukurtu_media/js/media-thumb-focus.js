/**
 * @file
 * Prevents the external embed media library modal from scrolling to the top
 * after a thumbnail is uploaded.
 *
 * Root cause: after upload AJAX resolves, ajax.js (line 1113) walks the
 * triggering button's ancestor chain and calls .trigger('focus') on the first
 * ancestor still in the DOM. That focus call scrolls the open dialog to the top.
 *
 * Primary fix: set jQuery data('disable-refocus', true) directly on the upload
 * and remove buttons each time they are rendered. This is what ajax.js reads at
 * line 1113 to decide whether to skip the ancestor-focus walk.
 *
 * Belt-and-suspenders: save dialog scroll position on file input change, then
 * restore it on ajaxComplete. ajaxComplete fires after the full command queue
 * AND the refocus step (see ajax.js lines 586-601), so restoring here undoes
 * any scroll the refocus may have caused.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      // Process the managed_file widget element every time it (re)appears after
      // an AJAX rebuild. The widget div's id contains "field-thumbnail" and it
      // carries the class js-form-managed-file.
      once(
        'mukurtu-thumb-widget',
        '[id*="field-thumbnail"].js-form-managed-file, [id*="field-thumbnail"] .js-form-managed-file',
        context
      ).forEach(function (widget) {
        // Mark all submit buttons (upload + remove) in this widget so ajax.js
        // will skip the ancestor-focus walk after their AJAX calls resolve.
        widget.querySelectorAll('input[type="submit"]').forEach(function (btn) {
          $(btn).data('disable-refocus', true);
        });

        var fileInput = widget.querySelector('input[type="file"]');
        if (!fileInput) {
          return;
        }

        fileInput.addEventListener('change', function () {
          // Use a document-level query rather than .closest() so this works
          // even when the widget is inside deeply nested AJAX wrappers.
          var scrollable = document.querySelector('.ui-dialog-content');
          if (!scrollable) {
            return;
          }

          var savedScroll = scrollable.scrollTop;
          $(document).one('ajaxComplete.thumbFocus', function () {
            scrollable.scrollTop = savedScroll;
          });
        });
      });
    }
  };

}(jQuery, Drupal, once));

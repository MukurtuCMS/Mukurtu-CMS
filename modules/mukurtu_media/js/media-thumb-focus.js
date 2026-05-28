/**
 * @file
 * Prevents the external embed media library modal from scrolling to the top
 * after a thumbnail is uploaded.
 *
 * Root cause: after upload AJAX resolves, ajax.js (line 1113) walks the
 * triggering element's ancestor chain and calls .trigger('focus') on the first
 * ancestor still in the DOM. That focus call scrolls the dialog back to the top.
 *
 * Primary fix: set jQuery data('disable-refocus', true) directly on the upload
 * and remove buttons so ajax.js skips the ancestor-focus walk. jQuery data is
 * used (not an HTML attribute) because ajax.js reads $(el).data('disable-refocus')
 * from the internal data cache, not from the DOM.
 *
 * Belt-and-suspenders: on file input change, save scrollTop and restore it on
 * ajaxComplete. ajaxComplete fires after the full command queue AND refocus
 * (see ajax.js lines 586-601), so restoring here undoes any residual scroll.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      // Process the managed_file widget element each time it appears in a new
      // context (after AJAX rebuild). The widget div's id contains
      // "field-thumbnail" and it carries the class js-form-managed-file.
      once(
        'mukurtu-thumb-widget',
        '[id*="field-thumbnail"].js-form-managed-file, [id*="field-thumbnail"] .js-form-managed-file',
        context
      ).forEach(function (widget) {
        // Mark every submit button inside the widget so ajax.js skips refocus.
        widget.querySelectorAll('input[type="submit"]').forEach(function (btn) {
          $(btn).data('disable-refocus', true);
        });

        // Belt-and-suspenders scroll restoration for any upload interactions.
        var fileInput = widget.querySelector('input[type="file"]');
        if (!fileInput) {
          return;
        }

        fileInput.addEventListener('change', function () {
          // Find the scrollable dialog container. Try the jQuery UI dialog
          // class first, then walk ancestors looking for overflow:auto/scroll.
          var scrollable = widget.closest('.ui-dialog-content');
          if (!scrollable) {
            var node = widget.parentElement;
            while (node && node !== document.documentElement) {
              var style = window.getComputedStyle(node);
              if (
                /(auto|scroll)/.test(style.overflowY) &&
                node.scrollHeight > node.clientHeight
              ) {
                scrollable = node;
                break;
              }
              node = node.parentElement;
            }
          }

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

/**
 * @file
 * Prevents the media library modal from jumping to the top after a thumbnail
 * is uploaded in the external embed add form.
 *
 * Root cause: after upload AJAX resolves, Drupal's ajax.js walks the
 * triggering element's ancestors and calls .trigger('focus') on the first
 * ancestor still in the DOM (the fields container, at the top of the form).
 * That focus call scrolls the .ui-dialog-content to the top.
 *
 * Primary fix: the PHP process callback sets #ajax['disable-refocus'] = TRUE
 * on the upload/remove buttons. RenderElementBase::preRenderAjaxForm() reads
 * this key (note: hyphen, not underscore) and writes data-disable-refocus="true"
 * to the button HTML. ajax.js checks that attribute and skips the refocus.
 *
 * Belt-and-suspenders JS layer:
 * - Trigger: input[type="file"] 'change' — fires when the user picks a file,
 *   which is the actual interaction (the upload button itself is js-hide and
 *   clicked programmatically).
 * - Restoration hook: ajaxComplete — per the comment in core/misc/ajax.js,
 *   this event is delayed until the entire async command queue (including the
 *   refocus call) resolves. Restoring scrollTop at that point undoes any
 *   scroll the refocus may have caused.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
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

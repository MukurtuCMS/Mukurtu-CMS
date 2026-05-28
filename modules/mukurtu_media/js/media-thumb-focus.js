/**
 * @file
 * Prevents the media library modal from jumping to the top after a thumbnail
 * is uploaded in the external embed add form.
 *
 * Root cause: Drupal's AJAX refocus logic walks up the triggering element's
 * ancestor chain and calls .trigger('focus') on the first ancestor still in
 * the DOM. That focus call scrolls the browser (and the modal) to that element.
 * The PHP form alter sets disable_refocus on the upload/remove buttons to stop
 * this, but as a belt-and-suspenders measure this behavior also scrolls the
 * thumbnail wrapper back into view whenever Drupal re-attaches to a wrapper
 * that already contains an uploaded file.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuThumbFocus = {
    attach: function (context) {
      // Drupal calls attachBehaviors() with the replaced element as context
      // after every AJAX response. once() fires for each new DOM element, so
      // this handler runs whenever a thumbnail wrapper is added or replaced.
      once('mukurtu-thumb-scroll', '[id*="field-thumbnail-wrapper"]', context).forEach(function (el) {
        var scrollable = el.closest('.ui-dialog-content');
        if (!scrollable) return;

        // Only scroll when a file is present (post-upload state). The remove
        // button is rendered only after an upload, so its absence means this
        // is the initial empty widget attachment — skip it.
        if (!el.querySelector('[id$="-remove-button"]')) return;

        // Remove ajax-new-content to suppress any residual scroll-to-new-
        // content behaviour Drupal may have triggered before behaviors ran.
        el.querySelectorAll('.ajax-new-content').forEach(function (node) {
          node.classList.remove('ajax-new-content');
        });

        // Scroll the thumbnail wrapper into the visible area of the modal.
        // requestAnimationFrame defers past any same-frame scroll operations.
        requestAnimationFrame(function () {
          var elRect = el.getBoundingClientRect();
          var scrollRect = scrollable.getBoundingClientRect();
          if (elRect.top < scrollRect.top) {
            scrollable.scrollTop -= (scrollRect.top - elRect.top) + 20;
          } else if (elRect.bottom > scrollRect.bottom) {
            scrollable.scrollTop += (elRect.bottom - scrollRect.bottom) + 20;
          }
        });
      });
    }
  };

}(Drupal, once));

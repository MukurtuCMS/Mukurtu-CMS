/**
 * @file
 * Restores click-to-focus on Tagify fields inside Paragraphs subforms.
 *
 * In Drupal 11.3, a mousedown handler in the Paragraphs/tabledrag stack calls
 * preventDefault(), stopping the browser from setting focus on the Tagify
 * contenteditable span when clicked. Tab navigation is unaffected.
 *
 * Fix: a single delegated click listener on document explicitly focuses the
 * contenteditable span after any click inside a <tags.tagify> element. Using
 * delegation means it works for both paragraphs present at page load and
 * those added later via AJAX, regardless of behavior-attachment order.
 *
 * See https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1684
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuTagifyFocus = {
    attach: function (context) {
      once('mukurtu-tagify-focus', document.body).forEach(function () {
        document.addEventListener('click', function (e) {
          const tagsEl = e.target.closest('tags.tagify');
          if (!tagsEl) {
            return;
          }
          // Don't steal focus from tag removal buttons or the dropdown.
          if (e.target.closest('.tagify__tag') || e.target.closest('.tagify__dropdown')) {
            return;
          }
          const input = tagsEl.querySelector('.tagify__input');
          if (input && document.activeElement !== input) {
            input.focus();
          }
        });
      });
    },
  };

})(Drupal, once);

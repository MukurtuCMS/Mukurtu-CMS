/**
 * @file
 * Restores click-to-focus on form fields inside Paragraphs subforms.
 *
 * In Drupal 11.3, a mousedown handler in the Paragraphs/tabledrag stack calls
 * preventDefault(), stopping the browser from setting focus on any interactive
 * element inside a draggable row when the user clicks it. This affects text
 * inputs, textareas, and Tagify contenteditable spans alike. Tab navigation
 * is unaffected because keyboard focus bypasses mousedown.
 *
 * Fix: a single delegated click listener on document explicitly focuses the
 * clicked element (or the Tagify contenteditable span) after any click inside
 * a Paragraphs content cell. Using delegation means it works for paragraphs
 * present at page load and those added later via AJAX.
 *
 * See https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1684
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuTagifyFocus = {
    attach: function (context) {
      once('mukurtu-tagify-focus', document.body).forEach(function () {
        document.addEventListener('click', function (e) {
          // Only act inside Paragraphs draggable content cells (not the drag
          // handle column).
          if (!e.target.closest('tr.draggable > td:not(.field-multiple-drag)')) {
            return;
          }

          // Tagify fields: focus the contenteditable span, not the hidden input.
          const tagsEl = e.target.closest('tags.tagify');
          if (tagsEl) {
            // Don't steal focus from tag removal buttons or the dropdown.
            if (e.target.closest('.tagify__tag') || e.target.closest('.tagify__dropdown')) {
              return;
            }
            const input = tagsEl.querySelector('.tagify__input');
            if (input && document.activeElement !== input) {
              input.focus();
            }
            return;
          }

          // Regular form fields: re-focus the element the click landed on.
          const focusable = e.target.closest(
            'input:not([type="hidden"]), textarea, select, [contenteditable]'
          );
          if (focusable && document.activeElement !== focusable) {
            focusable.focus();
          }
        });
      });
    },
  };

})(Drupal, once);

/**
 * @file
 * Copy-to-clipboard control for the citation field.
 *
 * Attaches to each [data-copy-citation] button, reads the plain-text
 * citation from the sibling [data-citation-text] element, and writes it to
 * the clipboard via navigator.clipboard. Announces success/failure through
 * the field item's [data-copy-citation-status] aria-live region.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.copyCitation = {
    attach(context) {
      once('copy-citation', '[data-copy-citation]', context).forEach(function (button) {
        const wrapper = button.closest('.field__item');
        const textEl = wrapper && wrapper.querySelector('[data-citation-text]');
        const statusEl = wrapper && wrapper.querySelector('[data-copy-citation-status]');
        if (!textEl || !statusEl) return;

        // Clear the live region before setting new text so repeat clicks with
        // an identical message still get re-announced by screen readers.
        function announce(message) {
          statusEl.textContent = '';
          window.setTimeout(function () {
            statusEl.textContent = message;
          }, 50);
        }

        button.addEventListener('click', function () {
          const text = textEl.textContent.trim();

          if (!navigator.clipboard || !navigator.clipboard.writeText) {
            announce(Drupal.t('Copy to clipboard is not supported in this browser.'));
            return;
          }

          navigator.clipboard.writeText(text).then(function () {
            announce(Drupal.t('Citation copied to clipboard'));
            button.classList.add('is-copied');
            window.setTimeout(function () {
              button.classList.remove('is-copied');
            }, 2000);
          }).catch(function () {
            announce(Drupal.t('Unable to copy citation. Please copy the text manually.'));
          });
        });
      });
    },
  };

})(Drupal, once);

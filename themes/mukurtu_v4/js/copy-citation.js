/**
 * @file
 * Copy-to-clipboard control for citation fields.
 *
 * Attaches to each [data-copy-citation] button, reads the plain-text
 * citation from the [data-citation-text] element in the same
 * [data-copy-citation-field] wrapper, and writes it to the clipboard via
 * navigator.clipboard. Announces success/failure through that wrapper's
 * [data-copy-citation-status] aria-live region. The success message comes
 * from the button's data-copy-success attribute, so each field can announce
 * its own name. See templates/misc/copy-citation-button.html.twig.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.copyCitation = {
    attach(context) {
      once('copy-citation', '[data-copy-citation]', context).forEach(function (button) {
        const wrapper = button.closest('[data-copy-citation-field]');
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
          // innerText (not textContent) copies the text as rendered: Twig
          // indentation collapses to single spaces, while line breaks a site
          // put in its citation template are kept. Non-breaking spaces (the
          // knowledge keepers date) become plain spaces.
          const text = textEl.innerText.replace(/\u00a0/g, ' ').trim();
          const successMessage = button.dataset.copySuccess || Drupal.t('Citation copied to clipboard');

          if (!navigator.clipboard || !navigator.clipboard.writeText) {
            announce(Drupal.t('Copy to clipboard is not supported in this browser.'));
            return;
          }

          navigator.clipboard.writeText(text).then(function () {
            announce(successMessage);
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

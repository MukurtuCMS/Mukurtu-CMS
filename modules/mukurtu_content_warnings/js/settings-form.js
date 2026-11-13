/**
 * @file
 * Keeps each tag warning's Remove button named after the row's selected term.
 */

(function (Drupal, once) {
  'use strict';

  // Drupal.t() escapes placeholders, which an aria-label would show as text.
  function plain(text) {
    return new DOMParser().parseFromString(text, 'text/html').documentElement.textContent;
  }

  function updateLabel(select) {
    const remove = select.closest('fieldset')?.querySelector('[data-mukurtu-warning-remove]');
    if (!remove) {
      return;
    }
    const label = select.value
      ? Drupal.t('Remove taxonomy warning for @term', {'@term': select.selectedOptions[0].text})
      : Drupal.t('Remove empty taxonomy warning row');
    remove.setAttribute('aria-label', plain(label));
  }

  /**
   * The server names the button after the saved term, which goes stale as
   * soon as a different term is picked, and after an AJAX rebuild.
   */
  Drupal.behaviors.mukurtuWarningRemoveLabel = {
    attach: function (context) {
      once('mukurtu-warning-remove-label', '[data-mukurtu-warning-term]', context).forEach(function (select) {
        updateLabel(select);
        select.addEventListener('change', function () {
          updateLabel(select);
        });
      });
    }
  };

})(Drupal, once);

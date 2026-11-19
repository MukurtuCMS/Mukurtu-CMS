/**
 * @file
 * Keeps the Display options toggles' values in the browse form.
 *
 * Each toggle has a hidden field that the form submits with the filters, so
 * the setting waits for Apply like everything else instead of reloading the
 * page on its own (WCAG 3.2.2, #2619). The hidden field is named only once
 * the setting is changed or already in the URL.
 */
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.searchCollapseToggle = {
    attach(context) {
      once('searchCollapseToggle', '.search-collapse-toggle__input', context).forEach((input) => {
        const value = input.closest('.search-collapse-toggle__item')
          ?.querySelector(`.search-collapse-toggle__value[data-value-for="${input.dataset.param}"]`);
        if (!value) {
          return;
        }
        input.addEventListener('change', () => {
          value.name = input.dataset.param;
          value.value = input.checked ? input.dataset.onValue : input.dataset.offValue;
        });
      });
    },
  };

})(Drupal, once);

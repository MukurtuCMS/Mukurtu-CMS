(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.membershipScroll = {
    attach(context) {
      // Target the wrapper element itself so this re-runs after each AJAX
      // rebuild (the wrapper is replaced, giving once() a fresh element).
      once('membership-scroll', '#membership-wrapper', context).forEach((el) => {
        if ((drupalSettings.mukurtuMembership || {}).scrollToTable) {
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          const searchField = el.querySelector('.membership-add-row .form-autocomplete');
          if (searchField) requestAnimationFrame(() => searchField.focus());
        }
      });
    },
  };

  Drupal.behaviors.membershipAutocomplete = {
    attach(context) {
      once('membership-autocomplete', '.membership-add-row .form-autocomplete', context).forEach((input) => {
        const $input = $(input);
        const users = (drupalSettings.mukurtuMembership || {}).users || [];
        if (!users.length) return;

        function applyLocalSource() {
          const instance = $input.autocomplete('instance');
          if (!instance) return;

          // Replace the AJAX source with local client-side filtering.
          $input.autocomplete('option', {
            source(request, response) {
              const term = request.term.toLowerCase();
              response(
                term
                  ? users.filter(u => u.label.toLowerCase().includes(term)).slice(0, 20)
                  : users.slice(0, 20)
              );
            },
            minLength: 0,
          });
        }

        // Apply immediately if jQuery UI autocomplete is already initialised,
        // otherwise wait for it (avoids brittle setTimeout timing assumptions).
        if ($input.autocomplete('instance')) {
          applyLocalSource();
        }
        else {
          $input.one('autocompleteopen', applyLocalSource);
        }

        $input.on('focus', () => {
          applyLocalSource();

          // Show all results immediately when the field is empty.
          const instance = $input.autocomplete('instance');
          if (instance && input.value === '') {
            instance._suggest(users.slice(0, 20));
          }
        });
      });
    },
  };
}(jQuery, Drupal, drupalSettings, once));

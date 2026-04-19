(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.membershipAutocomplete = {
    attach(context) {
      once('membership-autocomplete', '.membership-add-row .form-autocomplete', context).forEach((input) => {
        const $input = $(input);
        const users = (drupalSettings.mukurtuMembership || {}).users || [];
        if (!users.length) return;

        $input.on('focus', () => {
          setTimeout(() => {
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

            // Show all results immediately when the field is empty.
            if (input.value === '') {
              instance._suggest(users.slice(0, 20));
            }
          }, 50);
        });
      });
    },
  };
}(jQuery, Drupal, drupalSettings, once));

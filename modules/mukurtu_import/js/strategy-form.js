/**
 * @file
 * Auto-fills the Column Name when a Target Field is selected.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuImportStrategyFormAutoFill = {
    attach: function (context) {
      const selects = once(
        'mukurtu-strategy-autofill',
        'table select[name*="[target]"]',
        context
      );

      selects.forEach(function (select) {
        select.addEventListener('change', function () {
          const row = select.closest('tr');
          if (!row) {
            return;
          }
          const sourceInput = row.querySelector('input[name*="[source]"]');
          if (!sourceInput || sourceInput.value.trim() !== '') {
            return;
          }
          const selectedOption = select.options[select.selectedIndex];
          if (selectedOption && select.value !== '-1') {
            sourceInput.value = selectedOption.text;
          }
        });
      });
    }
  };

})(Drupal, once);

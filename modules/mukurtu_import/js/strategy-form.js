/**
 * @file
 * Behaviors for the Import Configuration Template form.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Auto-fills Column Name when a Target Field is selected.
   */
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

  /**
   * Progressively enhances the Identifier Column textfield into a select.
   *
   * Replaces the textfield with a select element whose options are built
   * from the current Column Name values in the mapping table. Updates
   * dynamically as source inputs change.
   */
  Drupal.behaviors.mukurtuImportStrategyIdentifierColumn = {
    attach: function (context) {
      const textfields = once(
        'mukurtu-strategy-identifier',
        'input[name="identifier_column"]',
        context
      );

      textfields.forEach(function (textfield) {
        const currentValue = textfield.value;

        // Create the select element.
        const select = document.createElement('select');
        select.name = textfield.name;
        select.id = textfield.id;

        // Copy over CSS classes, swapping text-specific for select-specific.
        if (textfield.classList.length) {
          select.className = textfield.className;
          select.classList.remove('form-element--type-text', 'form-text', 'form-element--api-textfield');
          select.classList.add('form-element--type-select', 'form-select');
        }
        if (textfield.hasAttribute('data-drupal-selector')) {
          select.setAttribute('data-drupal-selector', textfield.getAttribute('data-drupal-selector'));
        }

        // Replace the textfield with the select.
        textfield.parentNode.replaceChild(select, textfield);

        // Build initial options and restore value.
        rebuildOptions(select, currentValue);

        // Listen for changes on all current and future source inputs.
        const form = select.closest('form');
        if (form) {
          form.addEventListener('input', function (e) {
            if (e.target && e.target.classList.contains('mapping-source-input')) {
              rebuildOptions(select, select.value);
            }
          });

          // Also listen for change events (e.g. auto-fill from target select).
          form.addEventListener('change', function () {
            rebuildOptions(select, select.value);
          });
        }
      });

      // After AJAX rebuilds, the select may already exist but source inputs
      // have changed. Re-sync options.
      const existingSelects = document.querySelectorAll('select[name="identifier_column"]');
      existingSelects.forEach(function (select) {
        rebuildOptions(select, select.value);
      });
    }
  };

  /**
   * Rebuilds the options on the identifier column select.
   *
   * @param {HTMLSelectElement} select
   *   The identifier column select element.
   * @param {string} currentValue
   *   The current value to restore if still valid.
   */
  function rebuildOptions(select, currentValue) {
    const sourceInputs = document.querySelectorAll('.mapping-source-input');
    const sources = [];

    sourceInputs.forEach(function (input) {
      const value = input.value.trim();
      if (value !== '' && sources.indexOf(value) === -1) {
        sources.push(value);
      }
    });

    select.innerHTML = '';

    const computedOption = document.createElement('option');
    computedOption.value = '';
    computedOption.text = Drupal.t('- Computed -');
    select.appendChild(computedOption);

    sources.forEach(function (source) {
      const option = document.createElement('option');
      option.value = source;
      option.text = source;
      select.appendChild(option);
    });

    // Restore previous selection if still valid.
    if (currentValue && sources.indexOf(currentValue) !== -1) {
      select.value = currentValue;
    }
    else {
      select.value = '';
    }

    select.disabled = sources.length === 0;
  }

})(Drupal, once);

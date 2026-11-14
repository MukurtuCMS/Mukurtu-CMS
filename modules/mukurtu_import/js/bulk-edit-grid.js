/**
 * @file
 * Behaviors for the per-item bulk edit grid.
 */

((Drupal, once) => {
  /**
   * Moves focus to the per-item grid's error summary after a failed save.
   */
  Drupal.behaviors.mukurtuBulkEditGridErrors = {
    attach(context) {
      once('mukurtu-bulk-edit-grid-errors', '.mukurtu-bulk-edit-grid__error-summary', context).forEach((summary) => summary.focus());
    },
  };

  /**
   * Whether any cell differs from the text it started with.
   *
   * Compares against the starting text the form carries, not each field's
   * default value: after a save with errors, the default is the editor's
   * own unsaved text.
   */
  const hasUnsavedChanges = (form) => [...form.querySelectorAll('input[name^="grid_originals["]')].some((hidden) => {
    const key = hidden.name.slice('grid_originals['.length, -1);
    let originals = {};
    try {
      originals = JSON.parse(hidden.value) || {};
    }
    catch (e) {
      return false;
    }
    return Object.entries(originals).some(([field, text]) => {
      const cell = form.querySelector(`[name="${CSS.escape(`grid[${key}][${field}]`)}"]`);
      return cell && cell.value !== text;
    });
  });

  /**
   * Asks before an Edit link leaves unsaved changes behind.
   */
  Drupal.behaviors.mukurtuBulkEditGridEditLinks = {
    attach(context) {
      once('mukurtu-bulk-edit-grid-edit', '.mukurtu-bulk-edit-grid__edit', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          const form = link.closest('form');
          // eslint-disable-next-line no-alert
          if (form && hasUnsavedChanges(form) && !window.confirm(Drupal.t('You have unsaved changes in the grid. Leave without saving them?'))) {
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);

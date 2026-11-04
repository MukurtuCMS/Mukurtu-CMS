/**
 * @file
 * Moves focus to the per-item grid's error summary after a failed save.
 */

((Drupal, once) => {
  Drupal.behaviors.mukurtuBulkEditGridErrors = {
    attach(context) {
      once('mukurtu-bulk-edit-grid-errors', '.mukurtu-bulk-edit-grid__error-summary', context).forEach((summary) => summary.focus());
    },
  };
})(Drupal, once);

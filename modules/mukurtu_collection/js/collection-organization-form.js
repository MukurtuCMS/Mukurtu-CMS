/**
 * @file
 * Behaviors for the Collection Organization form.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Announces collection table changes and restores focus after AJAX
   * rebuilds.
   *
   * The Add/Remove buttons partially replace the table via #ajax, which
   * destroys the clicked Remove button and silently drops focus to <body>.
   * This restores focus to a stable control and reports the change via a
   * live region for screen reader users.
   *
   * The table element itself is what addCollectionToTableCallback() marks
   * with [data-collections-just-changed] (see CollectionOrganizationForm.php)
   * - not the wrapper div - because once()'s context is that wrapper div
   * itself when Drupal.attachBehaviors() runs after the AJAX swap, and
   * context.querySelectorAll() only matches descendants of context, never
   * context itself.
   */
  Drupal.behaviors.mukurtuCollectionOrganizationStatus = {
    attach: function (context) {
      once(
        'mukurtu-collection-organization-status',
        'table[data-collections-just-changed]',
        context
      ).forEach(function (table) {
        table.removeAttribute('data-collections-just-changed');

        const statusRegion = document.getElementById('collections-table-status');
        if (statusRegion) {
          const rowCount = table.querySelectorAll('tbody tr').length;
          statusRegion.textContent = Drupal.formatPlural(
            rowCount,
            'Collection organization updated. 1 item in the collection.',
            'Collection organization updated. @count items in the collection.'
          );
        }

        const addButton = document.getElementById('collections-table-add-button');
        if (addButton) {
          addButton.focus();
        }
      });
    }
  };

})(Drupal, once);

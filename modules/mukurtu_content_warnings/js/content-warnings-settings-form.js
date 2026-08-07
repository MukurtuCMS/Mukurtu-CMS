/**
 * @file
 * Behaviors for the Content Warnings settings form.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Announces taxonomy warning row changes and restores focus after AJAX
   * rebuilds.
   *
   * The Add/Remove buttons partially replace the fieldset via #ajax, which
   * destroys the clicked Remove button and silently drops focus to <body>.
   * This restores focus to a stable control and reports the change via a
   * live region for screen reader users.
   *
   * The fieldset element itself is what addMoreCallback() marks with
   * [data-warnings-just-changed] (see MukurtuContentWarningsSettingsForm.php)
   * - not the wrapper div - because once()'s context is that wrapper div
   * itself when Drupal.attachBehaviors() runs after the AJAX swap, and
   * context.querySelectorAll() only matches descendants of context, never
   * context itself.
   */
  Drupal.behaviors.mukurtuContentWarningsSettingsStatus = {
    attach: function (context) {
      once(
        'mukurtu-content-warnings-settings-status',
        'fieldset[data-warnings-just-changed]',
        context
      ).forEach(function (fieldset) {
        fieldset.removeAttribute('data-warnings-just-changed');

        const statusRegion = document.getElementById('taxonomy-warnings-status');
        if (statusRegion) {
          // Each remaining warning row is itself a nested <fieldset>, so
          // this excludes the outer fieldset (the matched element) and
          // counts only the rows.
          const rowCount = fieldset.querySelectorAll('fieldset').length;
          statusRegion.textContent = Drupal.formatPlural(
            rowCount,
            'Taxonomy warnings updated. 1 warning configured.',
            'Taxonomy warnings updated. @count warnings configured.'
          );
        }

        const addButton = document.getElementById('taxonomy-warnings-add-button');
        if (addButton) {
          addButton.focus();
        }
      });
    }
  };

})(Drupal, once);

/**
 * @file
 * Attaches show/hide functionality to checkboxes in the "Processor" tab.
 */

(($) => {
  Drupal.behaviors.searchApiProcessor = {
    attach(context) {
      const selector = '.search-api-status-wrapper input.form-checkbox';
      $(selector, context).each(function foreach() {
        const checkbox = this;
        const $checkbox = $(checkbox);
        const processorId = $checkbox.data('id');

        const $rows = $(
          `.search-api-processor-weight--${processorId}`,
          context,
        );
        const tab = $(
          `.search-api-processor-settings-${processorId}`,
          context,
        ).data('verticalTab');

        // Bind a click handler to this checkbox to conditionally show and hide
        // the processor's table row and vertical tab pane.
        $checkbox.on('click.searchApiUpdate', () => {
          if (checkbox.matches(':checked')) {
            $rows.show();
            if (tab) {
              tab.tabShow().updateSummary();
            }
          } else {
            $rows.hide();
            if (tab) {
              tab.tabHide().updateSummary();
            }
          }
        });

        // Attach summary for configurable items (only for screen-readers).
        if (tab) {
          tab.details.drupalSetSummary(() => {
            return checkbox.matches(':checked')
              ? Drupal.t('Enabled')
              : Drupal.t('Disabled');
          });
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.searchApiUpdate');
      });
    },
  };
})(jQuery);

/**
 * @file
 * Attaches show/hide functionality to checkboxes in the "Processor" tab.
 */

(function ($) {

  'use strict';

  Drupal.behaviors.facetsIndexFormatter = {
    attach: function (context, settings) {

      $('input.form-checkbox[data-processor-id]', context).each(function () {
        var $checkbox = $(this);
        var processor_id = $checkbox.data('processor-id');

        var $rows = $('.search-api-processor-weight--' + processor_id, context);

        // Bind a click handler to this checkbox to conditionally show and hide the processor's table row.
        $checkbox.on('click.updateProcessorState', function () {
          if ($checkbox.is(':checked')) {
            $rows.show();
          } else {
            $rows.hide();
          }
        });

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.updateProcessorState');
      });
    }
  };

})(jQuery);

(function ($, once) {
  /**
   * Invalid event handler for input elements in Details field group.
   */
  const onDetailsInvalid = function (e) {
    // Open any hidden parents first.
    $(e.target)
      .parents('details:not([open])')
      .each(function () {
        $(this).attr('open', '');
      });
  };

  /**
   * Behaviors for details validation.
   */
  Drupal.behaviors.fieldGroupDetailsValidation = {
    attach(context) {
      $(
        once(
          'field-group-details-validation',
          $('.field-group-details :input', context),
        ),
      ).on('invalid.field_group', onDetailsInvalid);
    },
  };
})(jQuery, once);

(function ($, once) {
  /**
   * Opens Tab field group with invalid input elements.
   */
  const fieldGroupTabsOpen = function ($fieldGroup) {
    if ($fieldGroup.data('verticalTab')) {
      $fieldGroup.data('verticalTab').tabShow();
    } else if ($fieldGroup.data('horizontalTab')) {
      $fieldGroup.data('horizontalTab').tabShow();
    } else {
      $fieldGroup.attr('open', '');
    }
  };

  /**
   * Behaviors for tab validation.
   */
  Drupal.behaviors.fieldGroupTabsValidation = {
    attach(context) {
      const $inputs = $('.field-group-tabs-wrapper :input', context);

      /**
       * Invalid event handler for input elements in Tabs field group.
       */
      const onTabsInvalid = function (e) {
        $inputs.off('invalid.field_group', onTabsInvalid);
        $(e.target)
          .parents(
            'details:not(:visible), details.horizontal-tab-hidden, details.vertical-tab-hidden',
          )
          .each(function () {
            fieldGroupTabsOpen($(this));
          });
        requestAnimationFrame(function () {
          $inputs.on('invalid.field_group', onTabsInvalid);
        });
      };

      $(once('field-group-tabs-validation', $inputs)).on(
        'invalid.field_group',
        onTabsInvalid,
      );
    },
  };
})(jQuery, once);

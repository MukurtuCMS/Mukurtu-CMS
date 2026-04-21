/**
 * @file
 * Contains Message UI JS.
 */
(function messageUiBehaviors($, Drupal, drupalSettings) {
  Drupal.behaviors.messageOwner = {
    attach: function attacheOwner(context) {
      $('.message-form-owner', context).drupalSetSummary(
        function ownerContext() {
          const name =
            $('.form-item-name input', context).val() ||
            drupalSettings.message_ui.anonymous;
          const date = $('.form-item-date input', context).val();
          return date
            ? Drupal.t('By @name on @date', { '@name': name, '@date': date })
            : Drupal.t('By @name', { '@name': name });
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings);

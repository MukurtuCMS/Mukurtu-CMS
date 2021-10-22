(function ($, Drupal) {
  Drupal.behaviors.mukurtu_community_record_tabs = {
    attach: function (context, settings) {
      $(document).ready(function () {
        $('details.js-form-wrapper.form-wrapper.horizontal-tabs-pane', context).drupalSetSummary(function (context) {
          let title = $(context).find('.field--name-title').html();
          return title;
        });
      });
    }
  }
})(jQuery, Drupal);

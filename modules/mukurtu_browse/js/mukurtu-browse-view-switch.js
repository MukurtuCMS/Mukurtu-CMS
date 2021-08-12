(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_view_switch = {
    attach: function (context, settings) {
      $('#mukurtu-browse-mode-switch-link', context).once('mukurtu-browse-view-switch').each(function () {
        $('#mukurtu-browse-mode-switch-link').on('click', function () {
          this.href = this.href + window.location.search;
        });
      });
    }
  };
})(jQuery, Drupal);

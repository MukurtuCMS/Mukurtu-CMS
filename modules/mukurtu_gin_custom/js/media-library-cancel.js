/**
 * Injects a visible "Cancel" button into the media library dialog footer.
 *
 * The actual remove button stays inside the form so Drupal can read its
 * #array_parents on submit. A proxy button in the footer delegates to it.
 */
(function ($) {
  function syncCancelProxy() {
    var $source = $('.js-media-library-add-form .js-mukurtu-cancel-source').first();
    var $buttonSet = $('.ui-dialog-buttonpane .ui-dialog-buttonset');

    // Remove stale proxy whenever we re-evaluate.
    $buttonSet.find('.js-mukurtu-cancel-proxy').remove();

    if (!$source.length || !$buttonSet.length) {
      return;
    }

    var $proxy = $('<input>', {
      type: 'button',
      'class': 'button js-mukurtu-cancel-proxy',
      value: Drupal.t('Cancel'),
    });

    $proxy.on('click', function () {
      // Drupal AJAX for submit buttons is triggered by mousedown then click.
      $source.trigger('mousedown').trigger('click');
    });

    $buttonSet.prepend($proxy);
  }

  $(document).ajaxComplete(syncCancelProxy);
})(jQuery);

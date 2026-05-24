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

  // Inject an Edit link at the end of each media library table row.
  // The checkbox column is hidden via CSS but kept in the DOM so row-click
  // selection (below) continues to work.
  function syncEditLinks() {
    var $table = $('.media-library-widget-modal .views-table');
    if (!$table.length) return;

    // Add a header cell for the edit column if not already present.
    var $thead = $table.find('thead tr');
    if (!$thead.find('.mukurtu-edit-col').length) {
      $thead.append('<th class="mukurtu-edit-col"></th>');
    }

    // Append an edit link to each body row that doesn't already have one.
    $table.find('tbody tr').each(function () {
      var $row = $(this);
      if ($row.find('.mukurtu-edit-col').length) return;
      var mediaId = parseInt($row.find('input[type="checkbox"]').val(), 10);
      if (!mediaId) return;
      $row.append(
        '<td class="mukurtu-edit-col">' +
        '<a href="/admin/media/' + mediaId + '/edit" target="_blank" rel="noopener">' +
        Drupal.t('Edit') +
        '</a>' +
        '</td>'
      );
    });
  }

  $(document).ajaxComplete(function () {
    syncCancelProxy();
    syncEditLinks();
  });

  // Highlight rows whose checkbox is checked.
  $(document).on(
    'change',
    '.media-library-widget-modal .views-table tbody tr input[type="checkbox"]',
    function () {
      $(this).closest('tr').toggleClass('mukurtu-row-selected', this.checked);
    }
  );

  // Stop edit link clicks from bubbling to the row selection handler.
  $(document).on('click', '.media-library-widget-modal .mukurtu-edit-col a', function (e) {
    e.stopPropagation();
  });

  // Make entire table rows in the media library selectable by delegating
  // clicks to the row's checkbox. Clicks directly on the checkbox are left
  // alone to avoid toggling twice.
  $(document).on(
    'click',
    '.media-library-widget-modal .views-table tbody tr',
    function (e) {
      if ($(e.target).is('input[type="checkbox"], label')) {
        return;
      }
      var $cb = $(this).find('input[type="checkbox"]');
      $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    }
  );
})(jQuery);

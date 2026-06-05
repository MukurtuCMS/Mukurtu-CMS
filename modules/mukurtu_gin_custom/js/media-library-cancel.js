/**
 * Injects a visible "Cancel" button into the media library dialog footer.
 *
 * The actual remove button stays inside the form so Drupal can read its
 * #array_parents on submit. A proxy button in the footer delegates to it.
 */
(function ($, Drupal, once, drupalSettings) {
  'use strict';

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
      'aria-label': Drupal.t('Cancel media upload'),
    });

    $proxy.on('click', function () {
      $source[0].click();
    });

    $buttonSet.prepend($proxy);
  }

  // Inject an Edit link at the end of each media library table row.
  // The checkbox column is visually hidden but kept in the accessibility tree
  // so row-click selection (below) and screen readers continue to work.
  function syncEditLinks() {
    var $table = $('.media-library-widget-modal .views-table');
    if (!$table.length) return;

    // Add a header cell for the edit column if not already present.
    var $thead = $table.find('thead tr');
    if (!$thead.find('.mukurtu-edit-col').length) {
      $thead.append('<th class="mukurtu-edit-col"></th>');
    }

    // Build a base URL from drupalSettings so subdirectory installs work.
    // drupalSettings.mukurtu.mediaEditUrl is set to the route for media ID 0
    // (e.g. /admin/media/0/edit). Replace the trailing /0/edit per row.
    var baseUrl = (drupalSettings.mukurtu && drupalSettings.mukurtu.mediaEditUrl)
      ? drupalSettings.mukurtu.mediaEditUrl.replace('/0/edit', '')
      : '/admin/media';

    // Append an edit link to each body row that doesn't already have one.
    $table.find('tbody tr').each(function () {
      var $row = $(this);
      if ($row.find('.mukurtu-edit-col').length) return;
      var mediaId = parseInt($row.find('input[type="checkbox"]').val(), 10);
      if (!mediaId) return;

      if (!$row.attr('tabindex')) {
        $row.attr('tabindex', '0');
      }

      // WCAG 4.1.2: expose selected state to assistive technology.
      var isChecked = $row.find('input[type="checkbox"]').prop('checked');
      $row.attr('aria-selected', isChecked ? 'true' : 'false');

      $row.append(
        '<td class="mukurtu-edit-col">' +
        '<a href="' + baseUrl + '/' + mediaId + '/edit" target="_blank" rel="noopener">' +
        Drupal.t('Edit') +
        '<span class="visually-hidden"> (' + Drupal.t('opens in new tab') + ')</span>' +
        '</a>' +
        '</td>'
      );
    });
  }

  Drupal.behaviors.mukurtuMediaLibraryCancel = {
    attach: function (context) {
      // Run sync functions once per attach cycle for any relevant dialog.
      once('mukurtu-cancel-proxy', '.ui-dialog-buttonpane', context).forEach(syncCancelProxy);
      once('mukurtu-edit-links', '.media-library-widget-modal .views-table', context).forEach(syncEditLinks);
    },
  };

  // Highlight rows whose checkbox is checked.
  $(document).on(
    'change',
    '.media-library-widget-modal .views-table tbody tr input[type="checkbox"]',
    function () {
      $(this).closest('tr')
        .toggleClass('mukurtu-row-selected', this.checked)
        .attr('aria-selected', this.checked ? 'true' : 'false');
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

  // Keyboard selection: Space or Enter on a focused row toggles its checkbox.
  $(document).on(
    'keydown',
    '.media-library-widget-modal .views-table tbody tr',
    function (e) {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        var $cb = $(this).find('input[type="checkbox"]');
        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
        // aria-selected is updated by the 'change' handler above.
      }
    }
  );

})(jQuery, Drupal, once, drupalSettings);

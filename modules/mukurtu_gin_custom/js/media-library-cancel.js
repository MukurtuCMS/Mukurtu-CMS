/**
 * Injects a visible "Cancel" button into the media library dialog footer.
 *
 * Visible whenever the add-media form is open. Clicking it closes the dialog.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  function syncCancelProxy() {
    var $addForm = $('.js-media-library-add-form');
    // Scope to .media-library-widget-modal so we don't touch unrelated dialogs.
    var $buttonSet = $('.media-library-widget-modal .ui-dialog-buttonpane .ui-dialog-buttonset');

    // Remove stale proxy whenever we re-evaluate.
    $buttonSet.find('.js-mukurtu-cancel-proxy').remove();

    if (!$addForm.length || !$buttonSet.length) {
      return;
    }

    var $proxy = $('<input>', {
      type: 'button',
      'class': 'button js-mukurtu-cancel-proxy',
      value: Drupal.t('Cancel'),
      'aria-label': Drupal.t('Cancel media upload'),
    });

    $proxy.on('click', function () {
      $addForm.closest('.ui-dialog-content').dialog('close');
    });

    $buttonSet.prepend($proxy);
  }

  // Re-inject the Cancel proxy after each button-pane rebuild triggered by
  // dialog.ajax.js. dialog:aftercreate bubbles, so we can listen on window.
  // We then attach a per-dialog listener for dialogButtonsChange (which does
  // NOT bubble), deferring one tick so jQuery UI finishes rebuilding the pane.
  window.addEventListener('dialog:aftercreate', function (e) {
    if (!$(e.target).closest('.media-library-widget-modal').length) {
      return;
    }
    syncCancelProxy();
    e.target.addEventListener('dialogButtonsChange', function () {
      setTimeout(syncCancelProxy, 0);
    });
  });

  // Inject an Edit link at the end of each media library table row.
  // The checkbox column is visually hidden but kept in the accessibility tree
  // so row-click selection (below) and screen readers continue to work.
  function syncEditLinks() {
    var $table = $('.media-library-widget-modal .views-table');
    if (!$table.length) return;

    // WCAG 4.1.2: role=grid is required for aria-selected on <tr> rows to be
    // announced correctly by NVDA/JAWS (plain <table> role does not expose it).
    if (!$table.attr('role')) {
      $table.attr('role', 'grid');
    }

    // Add a header cell for the edit column if not already present.
    var $thead = $table.find('thead tr');
    if (!$thead.find('.mukurtu-edit-col').length) {
      $thead.append('<th class="mukurtu-edit-col" aria-label="' + Drupal.t('Actions') + '"></th>');
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
    attach: function () {
      // Both functions are idempotent and must run on every attach cycle.
      // once() guards on outer dialog elements (.ui-dialog-buttonpane,
      // .media-library-widget-modal) would silently fail because Drupal passes
      // only the inner AJAX fragment as context — those wrappers are never
      // inside the context element, so once() never matches them post-AJAX.
      syncCancelProxy();
      syncEditLinks();
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

})(jQuery, Drupal, drupalSettings);

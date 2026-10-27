/**
 * @file
 * Shows the whole selection's size in the bulk edit page's action bar.
 *
 * claro/js/tableselect.js counts only the checked rows on the current page,
 * while VBO's "Selected N items" counts the selection across every page, so
 * the two disagree once a selection spans pages. VBO reports the total in
 * drupalSettings on load and in each AJAX response when the selection
 * changes. A MutationObserver puts the total back whenever tableselect.js
 * rewrites the status, using tableselect.js's own strings.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.mukurtuBulkEditSelectionCount = {
    attach(context) {
      once(
        'mukurtu-bulk-edit-selection-count',
        '.vbo-multipage-selector',
        context,
      ).forEach(function (selector) {
        const form = selector.closest('form');
        const status = form
          ? form.querySelector('[data-drupal-views-bulk-actions-status]')
          : null;
        if (!status) {
          return;
        }
        const viewId = selector.getAttribute('data-view-id');
        const displayId = selector.getAttribute('data-display-id');
        let total = ((drupalSettings.vbo_selected_count || {})[viewId] || {})[
          displayId
        ];

        function message() {
          return total > 0
            ? Drupal.formatPlural(total, '1 item selected', '@count items selected')
            : Drupal.t('No items selected');
        }

        function update() {
          if (typeof total === 'number' && status.textContent !== message()) {
            status.textContent = message();
          }
        }

        new MutationObserver(update).observe(status, {
          childList: true,
          characterData: true,
          subtree: true,
        });
        update();

        $(document).on('ajaxSuccess', function (event, xhr, settings) {
          const path = `views-bulk-operations/ajax/${viewId}/${displayId}`;
          const count = xhr.responseJSON ? xhr.responseJSON.count : undefined;
          if (settings.url && settings.url.includes(path) && typeof count === 'number') {
            total = count;
            update();
            Drupal.announce(message());
          }
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);

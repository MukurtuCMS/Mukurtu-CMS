/**
 * @file
 * Shows the right selection size in the bulk edit page's action bar.
 *
 * claro/js/tableselect.js counts the checked rows on the current page, and
 * counts every row, disabled ones too, when "select all" is used. The page
 * disables rows the editor can't edit, and VBO's "Selected N items" counts
 * the selection across every page, so the counts can disagree.
 *
 * With more than one page VBO keeps the total: in drupalSettings on load and
 * in each AJAX response when the selection changes. Otherwise the selection
 * is the checked, selectable rows. A MutationObserver puts the right count
 * back whenever tableselect.js rewrites the status, using its own strings.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.mukurtuBulkEditSelectionCount = {
    attach(context) {
      once(
        'mukurtu-bulk-edit-selection-count',
        '[data-drupal-views-bulk-actions-status]',
        context,
      ).forEach(function (status) {
        const form = status.closest('form');
        if (!form) {
          return;
        }
        const selector = form.querySelector('.vbo-multipage-selector');
        const viewId = selector ? selector.getAttribute('data-view-id') : '';
        const displayId = selector ? selector.getAttribute('data-display-id') : '';
        let total = selector
          ? ((drupalSettings.vbo_selected_count || {})[viewId] || {})[displayId]
          : undefined;

        function count() {
          return typeof total === 'number'
            ? total
            : form.querySelectorAll('.js-vbo-checkbox:checked').length;
        }

        function message() {
          const n = count();
          return n > 0
            ? Drupal.formatPlural(n, '1 item selected', '@count items selected')
            : Drupal.t('No items selected');
        }

        function update() {
          if (status.textContent !== message()) {
            status.textContent = message();
          }
        }

        new MutationObserver(update).observe(status, {
          childList: true,
          characterData: true,
          subtree: true,
        });
        update();

        if (selector) {
          $(document).on('ajaxSuccess', function (event, xhr, settings) {
            const path = `views-bulk-operations/ajax/${viewId}/${displayId}`;
            const response = xhr.responseJSON ? xhr.responseJSON.count : undefined;
            if (settings.url && settings.url.includes(path) && typeof response === 'number') {
              total = response;
              update();
              Drupal.announce(message());
            }
          });
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);

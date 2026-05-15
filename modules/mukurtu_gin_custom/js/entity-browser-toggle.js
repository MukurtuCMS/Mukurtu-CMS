/**
 * @file
 * Grid/list toggle for entity browser selected-items widgets.
 */
(function ($, once, Drupal) {
  Drupal.behaviors.entityBrowserViewToggle = {
    attach: function (context, settings) {
      once(
        'eb-view-toggle',
        '.field--name-field-items-in-collection .entities-list',
        context
      ).forEach(function (list) {
        var $list = $(list);

        var storageKey = 'mukurtu_eb_view_field_items_in_collection';
        var savedView = localStorage.getItem(storageKey) || 'list';

        function applyView(view) {
          $list.removeClass('eb-view-grid eb-view-list').addClass('eb-view-' + view);
          if (view === 'list') {
            $list.css({ display: 'block', 'grid-template-columns': '', gap: '' });
            $list.find('.item-container').css({ display: 'block' });
          } else {
            $list.css({ display: 'grid', 'grid-template-columns': 'repeat(auto-fill, minmax(150px, 1fr))', gap: '1rem' });
            $list.find('.item-container').css({ display: '' });
          }
        }

        applyView(savedView);

        var $toggle = $(
          '<div class="eb-view-toggle">' +
            '<button type="button" class="eb-view-toggle__btn eb-view-toggle__btn--list" aria-pressed="false">' +
              Drupal.t('List') +
            '</button>' +
            '<button type="button" class="eb-view-toggle__btn eb-view-toggle__btn--grid" aria-pressed="false">' +
              Drupal.t('Grid') +
            '</button>' +
          '</div>'
        );

        $toggle.find('.eb-view-toggle__btn--' + savedView).attr('aria-pressed', 'true');

        $list.before($toggle);

        $toggle.on('click', '.eb-view-toggle__btn', function () {
          var view = $(this).hasClass('eb-view-toggle__btn--grid') ? 'grid' : 'list';
          applyView(view);
          $toggle.find('.eb-view-toggle__btn').attr('aria-pressed', 'false');
          $(this).attr('aria-pressed', 'true');
          localStorage.setItem(storageKey, view);
        });
      });
    },
  };
})(jQuery, once, Drupal);

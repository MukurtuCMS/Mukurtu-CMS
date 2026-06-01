/**
 * @file
 * Grid/list toggle for entity browser selected-items widgets.
 */
(function ($, once, Drupal) {
  Drupal.behaviors.entityBrowserViewToggle = {
    attach: function (context, settings) {
      once(
        'eb-view-toggle',
        '.field--widget-entity-browser-entity-reference .entities-list',
        context
      ).forEach(function (list) {
        var $list = $(list);

        var $fieldWrapper = $list.closest('[class*="field--name-"]');
        var fieldClass = $fieldWrapper.attr('class') || '';
        var nameMatch = fieldClass.match(/field--name-([\w-]+)/);
        var storageKey = 'mukurtu_eb_view_' + (nameMatch ? nameMatch[1] : 'default');

        // Fields that should always display as grid with no toggle.
        var alwaysGrid = $fieldWrapper.hasClass('field--name-field-communities');

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

        if (alwaysGrid) {
          applyView('grid');
          return;
        }

        var savedView = localStorage.getItem(storageKey) || 'list';
        applyView(savedView);

        // 4.1.2 / 1.3.1: Wrap the two toggle buttons in a <fieldset> with a
        // visually-hidden <legend> so screen readers announce the group purpose.
        // aria-pressed on each button communicates the current state (2.4.3).
        // The legend is hidden visually but remains in the accessibility tree.
        var $toggle = $(
          '<fieldset class="eb-view-toggle">' +
            '<legend class="visually-hidden">' + Drupal.t('Display selected items as') + '</legend>' +
            '<button type="button" class="eb-view-toggle__btn eb-view-toggle__btn--list" aria-pressed="false">' +
              Drupal.t('List') +
            '</button>' +
            '<button type="button" class="eb-view-toggle__btn eb-view-toggle__btn--grid" aria-pressed="false">' +
              Drupal.t('Grid') +
            '</button>' +
          '</fieldset>'
        );

        $toggle.find('.eb-view-toggle__btn--' + savedView).attr('aria-pressed', 'true');

        var $openBtn = $list.closest('details, .field--widget-entity-browser-entity-reference').find('input[data-uuid]').first();
        if ($openBtn.length) {
          $openBtn.after($toggle);
        } else {
          $list.before($toggle);
        }

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

(($, Drupal, once, Toastify) => {
  Drupal.behaviors.ginLbToastify = {
    attach: (context) => {
      const offset = $('.ui-dialog-off-canvas').length
        ? $('.ui-dialog-off-canvas').width()
        : 0;

      once('glb-messages-warning', '.glb-messages--warning', context).forEach(
        (item) => {
          if ($(item).hasClass('toastify')) {
            return;
          }
          Toastify({
            text: $(item).html(),
            escapeMarkup: false,
            close: true,
            gravity: 'bottom',
            duration: 6000,
            position: 'right',
            offset: {
              x: 0,
            },
            className: 'glb-messages glb-messages--warning',
            style: {
              background: 'var(--colorGinWarningBackground)',
            },
          }).showToast();
          $(item).hide();
        },
      );
      once('glb-messages-error', '.glb-messages--error', context).forEach(
        (item) => {
          if ($(item).hasClass('toastify')) {
            return;
          }
          Toastify({
            text: $(item).html(),
            escapeMarkup: false,
            gravity: 'bottom',
            duration: 6000,
            position: 'right',
            close: true,
            offset: {
              x: offset,
            },
            className: 'glb-messages glb-messages--error',
            style: {
              background: 'var(--colorGinErrorBackground)',
            },
          }).showToast();
          $(item).hide();
        },
      );
      once('glb-messages-status', '.glb-messages--status', context).forEach(
        (item) => {
          if ($(item).hasClass('toastify')) {
            return;
          }

          if ($(item).parents('.glb-sidebar__content').length >= 1) {
            return;
          }

          Toastify({
            text: $(item).html(),
            escapeMarkup: false,
            close: true,
            gravity: 'bottom',
            duration: 6000,
            position: 'right',
            offset: {
              x: offset,
            },
            className: 'glb-messages glb-messages--status',
            style: {
              background: 'var(--colorGinStatusBackground)',
            },
          }).showToast();
          $(item).hide();
        },
      );
    },
  };
})(jQuery, Drupal, once, Toastify);

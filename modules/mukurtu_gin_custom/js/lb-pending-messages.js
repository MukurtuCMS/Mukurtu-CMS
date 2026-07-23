/**
 * Surfaces the "layout saved" confirmation, which has nowhere else to render.
 *
 * The Layout Builder edit page's template has no "highlighted" region (or
 * any other block region besides "content"), so "The layout override has
 * been saved." -- queued via core's Messenger service on save -- has
 * nowhere to display through the normal block/region system on this page.
 * Toastify (already loaded here for gin_lb's own use) is driven directly
 * instead, with no auto-hide (duration: -1, manually dismissible).
 *
 * Checked once per page load, since gin_lb's default save_behavior redirects
 * back to this same page after a save rather than the node's canonical view.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuLbPendingStatusMessages = {
    attach: function (context) {
      once('lb-pending-status-messages', '#layout-builder', context).forEach(function () {
        fetch(Drupal.url('mukurtu-gin-custom/layout-builder/pending-messages'), {
          credentials: 'same-origin',
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (typeof Toastify === 'undefined') {
              return;
            }
            (data.statuses || []).forEach(function (message) {
              Toastify({
                text: message,
                escapeMarkup: false,
                close: true,
                gravity: 'bottom',
                position: 'right',
                duration: -1,
                className: 'glb-messages glb-messages--status',
                style: {
                  background: 'var(--colorGinStatusBackground)',
                },
              }).showToast();
            });
          });
      });
    },
  };

})(Drupal, once);

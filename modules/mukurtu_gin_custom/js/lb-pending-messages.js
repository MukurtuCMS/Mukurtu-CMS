/**
 * Surfaces queued Layout Builder warnings once an off-canvas dialog closes.
 *
 * Layout Builder's off-canvas dialogs (add/configure block, add/configure
 * section, remove/move forms, etc.) no longer have core's
 * "You have unsaved changes." warning injected into them directly, since
 * gin_lb's Toastify presentation collides visually with the off_canvas_top
 * dialog panel. Once the dialog closes there's nothing left to collide
 * with, so fetch any queued warning at that point and show it via Toastify
 * ourselves -- with no auto-hide, since gin_lb's own Toastify behavior
 * hardcodes a 6-second duration for every message, and a user doing a long
 * editing session may otherwise miss or forget the warning.
 */
(function ($, Drupal) {
  'use strict';

  window.addEventListener('dialog:afterclose', function (e) {
    var $element = $(e.target);
    if (!Drupal.offCanvas || !Drupal.offCanvas.isOffCanvas($element)) {
      return;
    }

    if (typeof Toastify === 'undefined') {
      return;
    }

    fetch(Drupal.url('mukurtu-gin-custom/layout-builder/pending-messages'), {
      credentials: 'same-origin',
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        (data.messages || []).forEach(function (message) {
          Toastify({
            text: message,
            escapeMarkup: false,
            close: true,
            gravity: 'bottom',
            position: 'right',
            duration: -1,
            className: 'glb-messages glb-messages--warning',
            style: {
              background: 'var(--colorGinWarningBackground)',
            },
          }).showToast();
        });
      });
  });

})(jQuery, Drupal);

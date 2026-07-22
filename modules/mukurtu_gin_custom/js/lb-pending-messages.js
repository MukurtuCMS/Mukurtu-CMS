/**
 * Surfaces queued Layout Builder warnings once an off-canvas dialog closes.
 *
 * Layout Builder's off-canvas dialogs (add/configure block, add/configure
 * section, remove/move forms, etc.) no longer have core's
 * "You have unsaved changes." warning injected into them directly: gin_lb's
 * Toastify presentation renders any status message as a viewport-fixed
 * toast, which visually collides with the off_canvas_top dialog panel no
 * matter where in the DOM the message markup lives. Once the dialog closes
 * there's nothing left to collide with, so fetch and display any queued
 * warning at that point instead.
 */
(function ($, Drupal) {
  'use strict';

  window.addEventListener('dialog:afterclose', function (e) {
    var $element = $(e.target);
    if (!Drupal.offCanvas || !Drupal.offCanvas.isOffCanvas($element)) {
      return;
    }

    Drupal.ajax({
      url: Drupal.url('mukurtu-gin-custom/layout-builder/pending-messages'),
      progress: { type: 'none' },
    }).execute();
  });

})(jQuery, Drupal);

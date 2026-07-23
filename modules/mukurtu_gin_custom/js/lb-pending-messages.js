/**
 * Surfaces Layout Builder messages that have nowhere else to render.
 *
 * The Layout Builder edit page's template has no "highlighted" region (or
 * any other block region besides "content"), so a message queued via core's
 * Messenger service -- "You have unsaved changes." warnings, "The layout
 * override has been saved." confirmations, etc. -- has nowhere to display
 * through the normal block/region system on this page. Toastify (already
 * loaded here for gin_lb's own use) is driven directly instead, with no
 * auto-hide (duration: -1, manually dismissible), since a user doing a
 * longer editing session may otherwise miss or forget a message.
 *
 * Status messages are checked once per page load (e.g. right after a save,
 * since gin_lb's default save_behavior redirects back to this same page).
 * Warning messages are checked when an off-canvas dialog closes -- debounced,
 * since Layout Builder reuses the same #drupal-off-canvas element across a
 * multi-step flow (e.g. picker -> configure form), so "close" fires on every
 * step, not just when the user is truly done; a check is only actually
 * fetched if no new off-canvas dialog opens within the debounce window.
 */
(function ($, Drupal, once) {
  'use strict';

  var DEBOUNCE_MS = 700;
  var pendingWarningCheck = null;

  function showToast(text, isWarning) {
    if (typeof Toastify === 'undefined') {
      return;
    }
    Toastify({
      text: text,
      escapeMarkup: false,
      close: true,
      gravity: 'bottom',
      position: 'right',
      duration: -1,
      className: isWarning ? 'glb-messages glb-messages--warning' : 'glb-messages glb-messages--status',
      style: {
        background: isWarning ? 'var(--colorGinWarningBackground)' : 'var(--colorGinStatusBackground)',
      },
    }).showToast();
  }

  function fetchPendingMessages() {
    return fetch(Drupal.url('mukurtu-gin-custom/layout-builder/pending-messages'), {
      credentials: 'same-origin',
    }).then(function (response) { return response.json(); });
  }

  Drupal.behaviors.mukurtuLbPendingStatusMessages = {
    attach: function (context) {
      once('lb-pending-status-messages', '#layout-builder', context).forEach(function () {
        fetchPendingMessages().then(function (data) {
          (data.statuses || []).forEach(function (message) { showToast(message, false); });
        });
      });
    },
  };

  window.addEventListener('dialog:aftercreate', function (e) {
    var $element = $(e.target);
    if (Drupal.offCanvas && Drupal.offCanvas.isOffCanvas($element) && pendingWarningCheck !== null) {
      window.clearTimeout(pendingWarningCheck);
      pendingWarningCheck = null;
    }
  });

  window.addEventListener('dialog:afterclose', function (e) {
    var $element = $(e.target);
    if (!Drupal.offCanvas || !Drupal.offCanvas.isOffCanvas($element)) {
      return;
    }

    if (pendingWarningCheck !== null) {
      window.clearTimeout(pendingWarningCheck);
    }
    pendingWarningCheck = window.setTimeout(function () {
      pendingWarningCheck = null;
      fetchPendingMessages().then(function (data) {
        (data.warnings || []).forEach(function (message) { showToast(message, true); });
      });
    }, DEBOUNCE_MS);
  });

})(jQuery, Drupal, once);

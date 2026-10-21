/**
 * Surfaces the "layout saved" confirmation, which has nowhere else to render.
 *
 * The Layout Builder edit page's template has no "highlighted" region (or
 * any other block region besides "content"), so "The layout override has
 * been saved." -- queued via core's Messenger service on save -- has
 * nowhere to display through the normal block/region system on this page.
 * Toastify (already loaded here for gin_lb's own use) is driven directly
 * instead, with no auto-hide (duration: -1, manually dismissible), alongside
 * Drupal.announce() so screen reader users are told the save happened too.
 *
 * If Toastify isn't available, the endpoint is never called at all, so the
 * queued message stays in the Messenger queue for the normal block/region
 * system to display on whatever next page can render it, rather than being
 * drained and silently lost.
 *
 * Checked once per page load, since gin_lb's default save_behavior redirects
 * back to this same page after a save rather than the node's canonical view.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuLbPendingStatusMessages = {
    attach: function (context) {
      once('lb-pending-status-messages', '#layout-builder', context).forEach(function (layoutBuilder) {
        if (typeof Toastify === 'undefined') {
          return;
        }

        fetch(Drupal.url('session/token'))
          .then(function (response) { return response.text(); })
          .then(function (token) {
            return fetch(Drupal.url('mukurtu-gin-custom/layout-builder/pending-messages'), {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'X-CSRF-Token': token },
            });
          })
          .then(function (response) { return response.ok ? response.json() : {}; })
          .then(function (data) {
            (data.statuses || []).forEach(function (message) {
              Drupal.announce(message);
              showToast(message, layoutBuilder);
            });
          })
          .catch(function () {
            // A failed check only means no toast; the save itself succeeded.
          });
      });
    },
  };

  /**
   * Shows a persistent status toast that keyboard users can dismiss.
   *
   * Toastify hardcodes an English aria-label on its close button and drops
   * focus to <body> when the toast is removed, so both are patched here. Its
   * own aria-live attribute is removed because Drupal.announce() already
   * speaks the message.
   */
  function showToast(message, fallbackFocus) {
    var returnFocus = null;
    var hadFocus = false;

    var toast = Toastify({
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
      callback: function () {
        if (!hadFocus) {
          return;
        }
        var target = returnFocus && document.contains(returnFocus) ? returnFocus : fallbackFocus;
        if (target === fallbackFocus && !target.hasAttribute('tabindex')) {
          target.setAttribute('tabindex', '-1');
        }
        target.focus();
      },
    }).showToast();

    var element = toast.toastElement;
    element.removeAttribute('aria-live');
    var close = element.querySelector('.toast-close');
    if (close) {
      close.setAttribute('aria-label', Drupal.t('Close'));
    }
    element.addEventListener('focusin', function (event) {
      if (!hadFocus && event.relatedTarget && !element.contains(event.relatedTarget)) {
        returnFocus = event.relatedTarget;
      }
      hadFocus = true;
    });
    element.addEventListener('focusout', function (event) {
      if (event.relatedTarget && !element.contains(event.relatedTarget)) {
        hadFocus = false;
      }
    });
  }

})(Drupal, once);

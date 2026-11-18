/**
 * @file
 * Keyboard fixes for Klaro's consent settings (#2747).
 *
 * - Klaro adds its consent settings button as the first element of <body>,
 *   so it was the first Tab stop on every page, ahead of "Skip to main
 *   content". Move it to just after the skip link. It's fixed in place on
 *   screen, so it doesn't move there.
 * - Klaro's consent window is a modal dialog (aria-modal="true"), but Tab
 *   left it while it stayed open, it opened with focus partway down its
 *   list, and Escape didn't close it. Keep Tab inside it, start on its first
 *   control, close it on Escape, and return focus to whatever opened it.
 *
 * Appended to klaro/klaro by mukurtu_core_library_info_alter(), after
 * klaro.drupal.js, so this runs after Klaro has added the button.
 */
((Drupal) => {
  const WINDOW = '.klaro .cookie-modal .cm-modal';
  const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  /**
   * The window's controls that can take focus, in order.
   */
  function controlsOf(window) {
    return Array.from(window.querySelectorAll(FOCUSABLE)).filter(
      (element) => element.getClientRects().length > 0,
    );
  }

  /**
   * Puts the button after the skip link.
   */
  function orderButton() {
    const button = document.getElementById('klaro_toggle_dialog');
    const skipLink = document.querySelector('body > a[href="#main-content"]');
    if (button && skipLink && skipLink.nextElementSibling !== button) {
      skipLink.after(button);
    }
  }

  /**
   * Manages focus while the consent window is open.
   */
  function manageWindow() {
    // The element outside the window that last had focus opens it. That
    // can be inside Klaro, such as a blocked embed's "Manage privacy
    // settings" link.
    let lastOutside = null;
    let opener = null;
    let open = null;

    document.addEventListener('focusin', (event) => {
      if (!event.target.closest('.cookie-modal')) {
        if (open && open.isConnected) {
          // Focus can't leave an open modal dialog.
          controlsOf(open)[0]?.focus();
          return;
        }
        lastOutside = event.target;
      }
    });

    document.addEventListener('keydown', (event) => {
      if (!open || !open.isConnected) {
        return;
      }
      if (event.key === 'Escape' || event.key === 'Esc') {
        const close = open.querySelector('.hide');
        if (close) {
          event.preventDefault();
          close.click();
        }
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      const controls = controlsOf(open);
      const first = controls[0];
      const last = controls[controls.length - 1];
      if (!first) {
        return;
      }
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }, true);

    // Klaro draws and removes the window itself, so watch for it.
    const check = () => {
      const current = document.querySelector(WINDOW);
      if (current && current !== open) {
        open = current;
        opener = lastOutside;
        // After Klaro's own focus, which lands partway down the list.
        setTimeout(() => controlsOf(current)[0]?.focus(), 0);
      }
      else if (!current && open) {
        open = null;
        const target = opener && opener.isConnected
          ? opener
          : document.getElementById('klaro_toggle_dialog');
        opener = null;
        target?.focus();
      }
    };
    new MutationObserver(check).observe(document.body, { childList: true, subtree: true });
  }

  Drupal.behaviors.mukurtuKlaroKeyboard = {
    attach(context) {
      if (context !== document) {
        return;
      }
      orderButton();
      if (!document.body.dataset.mukurtuKlaroKeyboard) {
        document.body.dataset.mukurtuKlaroKeyboard = 'true';
        manageWindow();
      }
    },
  };
})(Drupal);

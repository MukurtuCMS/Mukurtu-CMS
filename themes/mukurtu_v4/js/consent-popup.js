/**
 * @file
 * Makes the contrib consent_popup block behave as a modal dialog. The
 * module (modules/contrib/consent_popup) only toggles a body class to show
 * and hide it, so while it's open the page behind the backdrop stays in the
 * tab order and focus starts behind it (#2583):
 *
 * - While the body has .consent-popup-opened, everything outside the popup
 *   is inert, and focus moves to the dialog so a screen reader announces it.
 * - "I decline" (when it doesn't redirect) replaces the text and removes the
 *   buttons, taking focus with them, so focus moves to the new message. It
 *   also reveals the "keep browsing" link, which the template hides from
 *   the tab order and screen readers while it's visually hidden.
 * - Closing the popup releases the page and starts focus again from the top.
 *
 * Watches the body class rather than binding to the module's buttons, so it
 * doesn't depend on which behavior attaches first.
 */

((Drupal, once) => {

  // Sets inert on every sibling of each ancestor of the popup, up to the
  // body, and returns the elements it changed so they can be restored.
  // Elements that were already inert are left alone.
  function makeRestInert(popup) {
    const changed = [];
    for (let el = popup; el && el !== document.body; el = el.parentElement) {
      Array.from(el.parentElement.children).forEach((sibling) => {
        if (sibling !== el && !sibling.inert) {
          sibling.inert = true;
          changed.push(sibling);
        }
      });
    }
    return changed;
  }

  Drupal.behaviors.consentPopupDialog = {
    attach(context) {
      once('consentPopupDialog', '#javali-popup', context).forEach((popup) => {
        let changed = null;

        const sync = () => {
          const open = document.body.classList.contains('consent-popup-opened');
          if (open && !changed) {
            changed = makeRestInert(popup);
            popup.focus();
          }
          else if (!open && changed) {
            changed.forEach((el) => {
              el.inert = false;
            });
            changed = null;
            // The popup usually sits in the footer, and the browser would
            // carry on tabbing from there. Start again from the top of the
            // page, where the skip link is.
            document.body.tabIndex = -1;
            document.body.focus();
            document.body.removeAttribute('tabindex');
          }
        };
        sync();
        new MutationObserver(sync).observe(document.body, {
          attributes: true,
          attributeFilter: ['class'],
        });

        // The module rewrites the popup on its own click handler; wait for
        // it to finish before moving focus. A redirecting decline leaves the
        // buttons in place, so focus stays put.
        popup.addEventListener('click', (event) => {
          if (!event.target.closest('.decline')) {
            return;
          }
          setTimeout(() => {
            if (popup.querySelector('.decline')) {
              return;
            }
            popup.querySelectorAll('a[aria-hidden]:not(.visually-hidden)').forEach((link) => {
              link.removeAttribute('aria-hidden');
              link.removeAttribute('tabindex');
            });
            const message = popup.querySelector('.consent-text');
            message.tabIndex = -1;
            message.focus();
          });
        });
      });
    },
  };

})(Drupal, once);

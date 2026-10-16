/**
 * @file
 * Lets Escape close the trays in Gin's secondary toolbar (#2397).
 *
 * Gin opens the Shortcuts, Dashboards and user menu trays with CSS alone, on
 * :hover and :focus-within, so nothing closed an open tray except moving the
 * pointer or focus away. WCAG 1.4.13 requires content shown on hover or focus
 * to be dismissible without either.
 *
 * Escape marks the open tab as dismissed, which mukurtu-gin-custom.css hides,
 * and returns focus from the tray to its tab. The mark is cleared once both
 * the pointer and focus have left the tab, so the tray opens normally the next
 * time.
 */
((Drupal, once) => {
  const dismissed = 'mukurtu-tray-dismissed';

  const restore = (tab) => {
    if (!tab.matches(':hover, :focus-within')) {
      tab.classList.remove(dismissed);
    }
  };

  Drupal.behaviors.mukurtuGinSecondaryTrayEscape = {
    attach(context) {
      once('mukurtu-gin-secondary-tray-escape', '.toolbar-secondary .toolbar-tab', context).forEach((tab) => {
        tab.addEventListener('mouseleave', () => restore(tab));
        tab.addEventListener('focusout', (event) => {
          if (!tab.contains(event.relatedTarget) && !tab.matches(':hover')) {
            tab.classList.remove(dismissed);
          }
        });
      });

      once('mukurtu-gin-secondary-tray-escape', 'html', context).forEach(() => {
        document.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape') {
            return;
          }
          document.querySelectorAll('.toolbar-secondary .toolbar-tab').forEach((tab) => {
            const tray = tab.querySelector('.toolbar-tray');
            if (!tray || tab.classList.contains(dismissed) || !tray.checkVisibility()) {
              return;
            }
            const focusInTray = tray.contains(document.activeElement);
            tab.classList.add(dismissed);
            if (focusInTray) {
              tab.querySelector('.toolbar-item')?.focus();
            }
          });
        });
      });
    },
  };
})(Drupal, once);

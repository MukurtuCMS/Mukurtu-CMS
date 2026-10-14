/**
 * @file
 * Stops Tab getting stuck on "Expand sidebar" in Gin's collapsed sidebar.
 *
 * While the sidebar is collapsed, Gin's navigation.js takes over Tab on each
 * top-level sidebar item (handleKeydownTopLevel()): it calls preventDefault()
 * and focuses the next item in its own list, or the first focusable element in
 * the page content after the last one. That list includes the sidebar's user
 * menu button, which Gin's own CSS hides with display: none
 * (".gin--navigation .toolbar-link--user"). Focusing a hidden element does
 * nothing, so Tab on "Expand sidebar", the item just before it, never moves
 * focus. Keyboard users cannot Tab forward past the sidebar to the top bar or
 * the page. Confirmed against drupal/gin 5.0.15 and the 5.0.x branch.
 *
 * Mukurtu collapses the sidebar by default (gin-collapsed-defaults.js), so
 * every admin meets this until they expand it.
 *
 * This listens in the capture phase on the sidebar, so it runs before Gin's
 * listener on the item itself. It only takes over when the item Gin would
 * focus next is hidden, so if Gin fixes this upstream it stops intervening
 * rather than fighting the new behavior.
 */
((Drupal, once) => {
  // The same selectors navigation.js uses for its top-level items and for the
  // page content's focusable elements.
  const topLevelSelector = '.navigation__logo, .toolbar-menu > .toolbar-menu__item--level-1 > .toolbar-link';
  const focusableSelector = 'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, [href], button, [tabindex="-1"]';

  const isVisible = (el) => el.checkVisibility();

  /**
   * Finds the first visible focusable element in the page content.
   *
   * @param {HTMLElement} sidebar
   *   Gin's sidebar element.
   *
   * @return {HTMLElement|undefined}
   *   The element Gin means to focus after the last sidebar item.
   */
  const firstPageFocusable = (sidebar) => {
    let content = sidebar.nextElementSibling;
    while (content && content.tagName === 'SCRIPT') {
      content = content.nextElementSibling;
    }
    return content
      ? Array.from(content.querySelectorAll(focusableSelector)).find(isVisible)
      : undefined;
  };

  Drupal.behaviors.mukurtuGinCollapsedTabTrap = {
    attach(context) {
      once('mukurtu-gin-collapsed-tab-trap', '.admin-toolbar', context).forEach((sidebar) => {
        sidebar.addEventListener('keydown', (event) => {
          if (event.key !== 'Tab' || event.shiftKey || document.documentElement.classList.contains('admin-toolbar-expanded')) {
            return;
          }

          const items = Array.from(document.querySelectorAll(topLevelSelector));
          const index = items.indexOf(event.target);
          const next = items[index + 1];
          // Not a top-level item, or Gin's next stop works: leave it to Gin.
          if (index === -1 || !next || isVisible(next)) {
            return;
          }

          // Keep Gin's handler from swallowing the key either way. With nowhere
          // to send focus, the browser's own Tab order takes over.
          event.stopPropagation();
          const target = items.slice(index + 1).find(isVisible) || firstPageFocusable(sidebar);
          if (target) {
            event.preventDefault();
            target.focus();
          }
        }, true);
      });
    },
  };
})(Drupal, once);

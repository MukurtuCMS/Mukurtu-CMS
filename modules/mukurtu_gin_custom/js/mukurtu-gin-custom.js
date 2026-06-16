/**
 * @file
 * Mukurtu Gin custom behaviors.
 */

((Drupal, once) => {
  /**
   * On Layout Builder pages, close any toolbar tray that toolbar.js
   * auto-restores from localStorage (Shortcuts, Dashboard, Devel, Profile).
   * The gin_lb secondary toolbar renders these tabs and the restored-open tray
   * overlaps the layout builder UI and cannot be minimized by normal means.
   */
  Drupal.behaviors.mukurtuGinLayoutBuilderToolbar = {
    attach(context) {
      // The dashboard layout builder (/dashboards/*/layout) does not get
      // glb-body, so check the URL as a fallback.
      const isDashboardLayout =
        window.location.pathname.includes('/dashboards/') &&
        window.location.pathname.endsWith('/layout');

      if (!document.body.classList.contains('glb-body') && !isDashboardLayout) {
        return;
      }
      once('mukurtu-close-toolbar-trays', 'body', context).forEach(() => {
        // setTimeout(0) defers until after toolbar.js has finished its own
        // attach() and restored the active tray from localStorage. There is no
        // cleaner hook point for this — toolbar.js restores state synchronously
        // at the end of its attach(), so a zero-delay timeout reliably runs
        // after it without depending on a specific execution time.
        setTimeout(() => {
          // Close via Backbone model — this also writes the cleared state
          // back to sessionStorage so the anti-flicker IIFE won't re-open
          // the tray on the next page load.
          if (Drupal.toolbar?.models?.toolbarModel) {
            Drupal.toolbar.models.toolbarModel.set('activeTab', null);
          }
          // The toolbar anti-flicker IIFE (toolbar.js lines 7-38) reads
          // sessionStorage and directly adds is-active to a tray *before*
          // Drupal behaviors run, independently of the Backbone model.
          // That tray is not tracked by the model, so set('activeTab', null)
          // above doesn't remove it. Strip is-active from all remaining trays.
          document.querySelectorAll('.toolbar-tray.is-active').forEach(el => {
            el.classList.remove('is-active');
          });
        }, 0);
      });
    },
  };
})(Drupal, once);

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
      if (!document.body.classList.contains('glb-body')) {
        return;
      }
      once('mukurtu-close-toolbar-trays', 'body', context).forEach(() => {
        // setTimeout(0) defers until after toolbar.js has finished its own
        // attach() and restored the active tray from localStorage. There is no
        // cleaner hook point for this — toolbar.js restores state synchronously
        // at the end of its attach(), so a zero-delay timeout reliably runs
        // after it without depending on a specific execution time.
        setTimeout(() => {
          if (
            Drupal.toolbar &&
            Drupal.toolbar.models &&
            Drupal.toolbar.models.toolbarModel
          ) {
            Drupal.toolbar.models.toolbarModel.set('activeTab', null);
          }
        }, 0);
      });
    },
  };
})(Drupal, once);

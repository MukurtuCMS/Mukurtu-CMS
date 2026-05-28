/**
 * @file
 * Initialize media content warnings.
 */

((Drupal, once) => {
  Drupal.behaviors.contentWarnings = {
    attach: function (context) {
      once('mukurtu-content-warnings', '.mukurtu-content-warnings', context).forEach((element) => {
        // Hide any windowed embeds (object, iframe) inside the same .media
        // container so they don't paint over the warning overlay.
        this.setEmbedVisibility(element, false);

        // Watch for iframes/objects inserted later by lazy loaders (e.g. Blazy).
        this.watchForNewEmbeds(element);

        element.addEventListener('click', (event) => {
          event.preventDefault();
          this.dismissContentWarning(event.target.closest('.mukurtu-content-warnings'));
        });
      });
    },

    // Observe the .media container and hide any embed elements added after init.
    watchForNewEmbeds: function (warningEl) {
      const mediaEl = warningEl.closest('.media');
      if (!mediaEl) {
        return;
      }
      const observer = new MutationObserver(() => {
        if (!warningEl.classList.contains('dismissed')) {
          this.setEmbedVisibility(warningEl, false);
        }
        else {
          observer.disconnect();
        }
      });
      observer.observe(mediaEl, { childList: true, subtree: true });
      warningEl._contentWarningObserver = observer;
    },

    // Show or hide <object>/<iframe> siblings inside the parent .media element.
    setEmbedVisibility: function (warningEl, visible) {
      const mediaEl = warningEl.closest('.media');
      if (!mediaEl) {
        return;
      }
      mediaEl.querySelectorAll('object, iframe').forEach((embed) => {
        embed.style.visibility = visible ? '' : 'hidden';
      });
    },

    dismissContentWarning: function (element) {
      // Exit early if element has a .splide__track--nav parent.
      if (element.closest('.splide__track--nav')) {
        return;
      }

      const mid = element.dataset.mid;
      document.querySelectorAll(`.mukurtu-content-warnings[data-mid="${mid}"]`).forEach((contentWarnings) => {
        // Disconnect the mutation observer before restoring visibility.
        if (contentWarnings._contentWarningObserver) {
          contentWarnings._contentWarningObserver.disconnect();
          delete contentWarnings._contentWarningObserver;
        }
        contentWarnings.classList.add('dismissed');
        // Restore any hidden embeds now that the warning is dismissed.
        this.setEmbedVisibility(contentWarnings, true);
      });
    },
  };
})(Drupal, once);

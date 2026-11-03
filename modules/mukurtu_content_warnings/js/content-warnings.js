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

        // Block keyboard and AT users from reaching media controls behind the
        // overlay before the warning is dismissed (WCAG 2.1.1, 4.1.2).
        this.setMediaInert(element, true);

        // Watch for iframes/objects inserted later by lazy loaders (e.g. Blazy).
        this.watchForNewEmbeds(element);

        // A click anywhere on the overlay dismisses it. The dismiss button
        // inside it gives keyboard and AT users the same action, since a
        // native button fires click on Enter and Space.
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

      // The button is about to be hidden. If it has focus, move focus to the
      // media it was covering so keyboard users don't land on the page body.
      const hadFocus = element.contains(document.activeElement);

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
        // Restore keyboard/AT access to the media behind the overlay.
        this.setMediaInert(contentWarnings, false);
      });

      if (hadFocus) {
        const candidates = element.closest('.media')?.querySelectorAll('audio, video, object, iframe, a, button, [tabindex]:not([tabindex="-1"])') ?? [];
        const media = [...candidates].find((el) => !element.contains(el));
        media?.focus();
      }
    },

    // Mark or unmark interactive elements inside .media as inert so keyboard
    // and AT users cannot reach them while the content warning overlay is shown.
    setMediaInert: function (warningEl, inert) {
      const mediaEl = warningEl.closest('.media');
      if (!mediaEl) {
        return;
      }
      mediaEl.querySelectorAll('audio, video, object, iframe, a, button, [tabindex]').forEach((el) => {
        if (warningEl.contains(el)) {
          return;
        }
        if (inert) {
          el.setAttribute('inert', '');
        }
        else {
          el.removeAttribute('inert');
        }
      });
    },
  };
})(Drupal, once);

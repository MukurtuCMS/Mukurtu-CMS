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

        // Mark the overlay while its text scrolls, so the theme can put a
        // band behind the pinned button only then.
        if (window.ResizeObserver) {
          new ResizeObserver(() => {
            element.classList.toggle('mukurtu-content-warnings--scrolls', element.scrollHeight > element.clientHeight + 1);
          }).observe(element);
        }

        // Cards (themes' _card-media.html.twig) leave warned media unlinked and
        // pass the item's URL instead. A card is too small for the warnings,
        // so it shows the title, a link to the item, which shows every
        // warning, and the "Show media" button.
        const cardUrl = element.closest('[data-mukurtu-card-url]')?.dataset.mukurtuCardUrl;
        const hint = element.querySelector('.mukurtu-content-warnings__card-hint');
        if (cardUrl && hint) {
          element.classList.add('mukurtu-content-warnings--card');
          const link = document.createElement('a');
          link.href = cardUrl;
          link.textContent = hint.textContent.trim();
          hint.replaceChildren(link);
        }

        // Media still wrapped in a link that is hidden from AT and out of
        // the Tab order, since the card's title link goes to the same page.
        // A button there would be a Tab stop AT can't see (WCAG 4.1.2). A card
        // is too small to show every warning, so it shows only the title and
        // a click follows the link: the item page shows the full warnings
        // before the media can be revealed.
        if (element.closest('a')) {
          element.querySelector('.mukurtu-content-warnings__dismiss')?.setAttribute('tabindex', '-1');
          return;
        }

        // A click anywhere on the overlay dismisses it. The dismiss button
        // inside it gives keyboard and AT users the same action, since a
        // native button fires click on Enter and Space.
        element.addEventListener('click', (event) => {
          // Let the card's link to the item navigate.
          if (event.target.closest('a')) {
            return;
          }
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

      const mediaEl = element.closest('.media');
      if (hadFocus && mediaEl) {
        // Skip overlay controls and anything not rendered, such as the hidden
        // lightbox copy of the media nested inside this .media.
        const candidates = mediaEl.querySelectorAll('audio, video, object, iframe, a, button, [tabindex]:not([tabindex="-1"])');
        const target = [...candidates].find((el) => !el.closest('.mukurtu-content-warnings') && el.getClientRects().length > 0);
        if (target) {
          target.focus();
        }
        else {
          // Images have no control of their own, so focus the media itself.
          mediaEl.setAttribute('tabindex', '-1');
          mediaEl.focus();
        }
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
        // Skip every overlay's own controls, not only this one's: the hidden
        // lightbox copy of the media is nested inside this .media, and its
        // dismiss button must stay focusable once the lightbox shows it.
        if (el.closest('.mukurtu-content-warnings')) {
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

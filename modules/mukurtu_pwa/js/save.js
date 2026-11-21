/**
 * @file
 * The "Save to this device" button on item pages (#400 spike).
 */

((Drupal, once) => {
  /**
   * Lists the same-origin files this page needs, so they can be saved too.
   */
  function collectAssets() {
    const urls = new Set();
    const add = (value) => {
      if (!value) {
        return;
      }
      try {
        const url = new URL(value, window.location.href);
        if (url.origin === window.location.origin) {
          urls.add(url.pathname + url.search);
        }
      } catch {
        // Not a URL.
      }
    };
    const addSrcset = (value) =>
      (value || '')
        .split(',')
        .forEach((part) => add(part.trim().split(/\s+/)[0]));

    document
      .querySelectorAll('link[rel="stylesheet"][href]')
      .forEach((link) => add(link.getAttribute('href')));
    document
      .querySelectorAll('script[src]')
      .forEach((script) => add(script.getAttribute('src')));
    document.querySelectorAll('img').forEach((img) => {
      add(img.currentSrc);
      add(img.getAttribute('src'));
      // Lazy-loaded images keep their real address in data attributes.
      add(img.getAttribute('data-src'));
      addSrcset(img.getAttribute('srcset'));
      addSrcset(img.getAttribute('data-srcset'));
    });
    document.querySelectorAll('source').forEach((source) => {
      add(source.getAttribute('src'));
      addSrcset(source.getAttribute('srcset'));
      addSrcset(source.getAttribute('data-srcset'));
    });
    document.querySelectorAll('audio, video, track').forEach((media) => {
      add(media.getAttribute('src'));
      add(media.getAttribute('poster'));
    });
    // Documents and full-size images people open from the page.
    document
      .querySelectorAll('a[href*="/system/files/"], a[href*="/files/"]')
      .forEach((link) => add(link.getAttribute('href')));
    return [...urls];
  }

  Drupal.behaviors.mukurtuPwaSave = {
    attach(context) {
      if (!Drupal.mukurtuPwa || !Drupal.mukurtuPwa.supported) {
        return;
      }
      once('mukurtu-pwa-save', '.mukurtu-pwa-save', context).forEach(
        (wrapper) => {
          const button = wrapper.querySelector('.mukurtu-pwa-save__button');
          const status = wrapper.querySelector('.mukurtu-pwa-save__status');
          const url = window.location.pathname;
          let saved = false;

          const render = () => {
            button.textContent = saved
              ? Drupal.t('Remove from this device')
              : Drupal.t('Save to this device');
          };

          Drupal.mukurtuPwa
            .ask({ type: 'isSaved', url })
            .then((result) => {
              saved = Boolean(result);
              render();
              wrapper.hidden = false;
            })
            .catch(() => {});

          button.addEventListener('click', async () => {
            button.disabled = true;
            try {
              if (saved) {
                await Drupal.mukurtuPwa.ask({ type: 'remove', url });
                saved = false;
                status.textContent = Drupal.t('Removed from this device.');
              } else {
                status.textContent = Drupal.t('Saving to this device...');
                await Drupal.mukurtuPwa.ask({
                  type: 'save',
                  url,
                  title: wrapper.dataset.offlineTitle,
                  access: wrapper.dataset.offlineAccess,
                  assets: collectAssets(),
                });
                saved = true;
                status.textContent = Drupal.t(
                  'Saved. You can open this item without a connection.',
                );
              }
            } catch {
              status.textContent = Drupal.t(
                "Couldn't save this item. Connect to the internet and try again.",
              );
            }
            render();
            button.disabled = false;
          });
        },
      );
    },
  };
})(Drupal, once);

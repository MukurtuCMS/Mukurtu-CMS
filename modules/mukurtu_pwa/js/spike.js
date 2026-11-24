/**
 * @file
 * Tester pages for the #400 PWA spike: storage status and the upload outbox.
 */

((Drupal, once) => {
  const plain = (html) =>
    new DOMParser().parseFromString(html, 'text/html').documentElement
      .textContent;

  const megabytes = (bytes) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

  async function renderStatus(wrapper) {
    const set = (key, text) => {
      wrapper.querySelector(`[data-status="${key}"]`).textContent = plain(text);
    };
    const storage = await Drupal.mukurtuPwa.storage();
    const items = await Drupal.mukurtuPwa.ask({ type: 'listItems' });
    const version = await Drupal.mukurtuPwa.ask({ type: 'version' });
    set(
      'usage',
      Drupal.t('Storage used: @used of about @quota', {
        '@used': megabytes(storage.usage),
        '@quota': megabytes(storage.quota),
      }),
    );
    set(
      'persisted',
      Drupal.t('Persistent storage: @value', {
        '@value': storage.persisted
          ? Drupal.t('Granted')
          : Drupal.t('Not granted'),
      }),
    );
    set(
      'installed',
      Drupal.t('Installed as an app: @value', {
        '@value': Drupal.mukurtuPwa.standalone()
          ? Drupal.t('Yes')
          : Drupal.t('No'),
      }),
    );
    set(
      'version',
      Drupal.t('Service worker version: @version', { '@version': version }),
    );
    set(
      'items',
      Drupal.t('Items kept offline: @count', {
        '@count': items.filter((item) => item.present).length,
      }),
    );

    const opens = await Drupal.mukurtuPwa.ask({ type: 'opens' });
    const table = wrapper.querySelector('.mukurtu-pwa-status__opens');
    const body = table.tBodies[0] || table.createTBody();
    body.replaceChildren(
      ...opens.map((open) => {
        const row = document.createElement('tr');
        [
          new Date(open.time).toLocaleString(),
          `${open.itemsFound} / ${open.itemsTotal}`,
          megabytes(open.usage),
        ].forEach((value) => {
          const cell = document.createElement('td');
          cell.textContent = value;
          row.append(cell);
        });
        return row;
      }),
    );
  }

  function statusLabel(entry) {
    switch (entry.status) {
      case 'uploading':
        return Drupal.t('Uploading');
      case 'uploaded':
        return Drupal.t('Uploaded');
      case 'failed':
        return Drupal.t('Upload failed: @reason', { '@reason': entry.error });
      default:
        return Drupal.t('Waiting for a connection');
    }
  }

  async function renderOutbox(wrapper) {
    const entries = await Drupal.mukurtuPwa.ask({ type: 'outboxList' });
    wrapper.querySelector('.mukurtu-pwa-outbox__list').replaceChildren(
      ...entries.map((entry) => {
        const li = document.createElement('li');
        li.textContent = `${entry.title}: ${plain(statusLabel(entry))}`;
        return li;
      }),
    );
  }

  Drupal.behaviors.mukurtuPwaSpike = {
    attach(context) {
      if (!Drupal.mukurtuPwa || !Drupal.mukurtuPwa.supported) {
        return;
      }

      once('mukurtu-pwa-status', '.mukurtu-pwa-status', context).forEach(
        (wrapper) => {
          const message = wrapper.querySelector('.mukurtu-pwa-spike__message');
          renderStatus(wrapper).catch(() => {});
          wrapper.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', async () => {
              button.disabled = true;
              message.textContent = '';
              try {
                if (button.dataset.action === 'persist') {
                  await navigator.storage.persist();
                } else if (button.dataset.action === 'fill') {
                  await Drupal.mukurtuPwa.ask({
                    type: 'fillTestData',
                    megabytes: 100,
                  });
                } else {
                  await Drupal.mukurtuPwa.ask({ type: 'clearTestData' });
                }
              } catch (e) {
                message.textContent = String(e.message || e);
              }
              await renderStatus(wrapper).catch(() => {});
              button.disabled = false;
            });
          });
        },
      );

      once('mukurtu-pwa-outbox', '.mukurtu-pwa-outbox', context).forEach(
        (wrapper) => {
          const form = wrapper.querySelector('form');
          const message = wrapper.querySelector('.mukurtu-pwa-spike__message');
          renderOutbox(wrapper).catch(() => {});
          navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'outboxChanged') {
              renderOutbox(wrapper).catch(() => {});
            }
          });
          form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const title = form.elements.title.value.trim();
            const file = form.elements.photo.files[0];
            if (!title) {
              form.elements.title.focus();
              return;
            }
            if (!file) {
              form.elements.photo.focus();
              return;
            }
            // The bytes are read now and stored, not the File: Safari can
            // lose a stored File's contents before it uploads.
            await Drupal.mukurtuPwa.ask({
              type: 'outboxAdd',
              title,
              data: await file.arrayBuffer(),
              mimeType: file.type,
              name: file.name,
            });
            form.reset();
            message.textContent = Drupal.t(
              "Draft saved on this device. It uploads when you're online.",
            );
            await renderOutbox(wrapper);
            Drupal.mukurtuPwa.flush();
          });
        },
      );
    },
  };
})(Drupal, once);

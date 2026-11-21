/**
 * @file
 * Registers the service worker and reports each app open (#400 spike).
 */

((Drupal, drupalSettings) => {
  Drupal.mukurtuPwa = Drupal.mukurtuPwa || {};

  if (!('serviceWorker' in navigator)) {
    Drupal.mukurtuPwa.supported = false;
    return;
  }
  Drupal.mukurtuPwa.supported = true;

  const settings = drupalSettings.mukurtuPwa || {};

  /**
   * Sends a message to the service worker and resolves with its answer.
   */
  Drupal.mukurtuPwa.ask = async (message) => {
    const registration = await navigator.serviceWorker.ready;
    const worker = navigator.serviceWorker.controller || registration.active;
    return new Promise((resolve, reject) => {
      const channel = new MessageChannel();
      channel.port1.onmessage = (event) => {
        if (event.data && event.data.error) {
          reject(new Error(event.data.error));
        } else {
          resolve(event.data ? event.data.result : undefined);
        }
      };
      worker.postMessage(message, [channel.port2]);
    });
  };

  /**
   * Gets a CSRF token for the signed-in person, or null when signed out.
   */
  Drupal.mukurtuPwa.token = async () => {
    if (!drupalSettings.user || String(drupalSettings.user.uid) === '0') {
      return null;
    }
    const response = await fetch(settings.tokenUrl, {
      credentials: 'same-origin',
    });
    return response.ok ? response.text() : null;
  };

  /**
   * Describes this device's storage.
   */
  Drupal.mukurtuPwa.storage = async () => {
    const result = { usage: 0, quota: 0, persisted: false };
    if (navigator.storage && navigator.storage.estimate) {
      const estimate = await navigator.storage.estimate();
      result.usage = estimate.usage || 0;
      result.quota = estimate.quota || 0;
    }
    if (navigator.storage && navigator.storage.persisted) {
      result.persisted = await navigator.storage.persisted();
    }
    return result;
  };

  Drupal.mukurtuPwa.standalone = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    navigator.standalone === true;

  // Upload waiting drafts. iOS has no Background Sync, so this also runs
  // whenever a page opens or the connection comes back.
  Drupal.mukurtuPwa.flush = () =>
    Drupal.mukurtuPwa.ask({ type: 'outboxFlush' }).catch(() => false);

  async function reportOpen() {
    // Once per app launch, not once per page.
    try {
      if (sessionStorage.getItem('mukurtuPwaOpened')) {
        return;
      }
      sessionStorage.setItem('mukurtuPwaOpened', '1');
    } catch {
      // Without sessionStorage, report every page.
    }
    const storage = await Drupal.mukurtuPwa.storage();
    const report = await Drupal.mukurtuPwa.ask({
      type: 'logOpen',
      standalone: Drupal.mukurtuPwa.standalone(),
      ...storage,
    });
    const token = await Drupal.mukurtuPwa.token();
    if (!token || !navigator.onLine) {
      return;
    }
    await fetch(settings.reportUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({
        ...report,
        userAgent: navigator.userAgent,
      }),
    });
  }

  navigator.serviceWorker
    .register(settings.serviceWorker, { scope: settings.scope })
    .then(async () => {
      const uid = drupalSettings.user ? String(drupalSettings.user.uid) : '0';
      await Drupal.mukurtuPwa.ask({ type: 'session', uid });
      reportOpen().catch(() => {});
      Drupal.mukurtuPwa.flush();
    })
    .catch(() => {});

  window.addEventListener('online', () => Drupal.mukurtuPwa.flush());
})(Drupal, drupalSettings);

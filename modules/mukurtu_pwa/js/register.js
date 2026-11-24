/**
 * @file
 * Registers the service worker and reports each app open (#400 spike).
 */

((Drupal, drupalSettings) => {
  Drupal.mukurtuPwa = Drupal.mukurtuPwa || {};
  const settings = drupalSettings.mukurtuPwa || {};

  /**
   * Replaces an element's children. replaceChildren() needs Safari 14.
   */
  Drupal.mukurtuPwa.replaceChildren = (element, children) => {
    element.textContent = '';
    children.forEach((child) => element.append(child));
  };

  // A random version 4 UUID. crypto.randomUUID() needs Safari 15.4.
  function uuid() {
    if (window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join(
      '',
    );
    return [8, 12, 16, 20, 32]
      .map((end, i, ends) => hex.slice(i ? ends[i - 1] : 0, end))
      .join('-');
  }

  /**
   * Reports, once per launch, that this browser can't run the offline tests.
   *
   * Without this, a device that fails never shows up in the results.
   */
  function reportUnsupported(reason) {
    let deviceId;
    try {
      if (sessionStorage.getItem('mukurtuPwaUnsupported')) {
        return;
      }
      sessionStorage.setItem('mukurtuPwaUnsupported', '1');
      deviceId = localStorage.getItem('mukurtuPwaDeviceId');
      if (!deviceId) {
        deviceId = uuid();
        localStorage.setItem('mukurtuPwaDeviceId', deviceId);
      }
    } catch (e) {
      deviceId = deviceId || uuid();
    }
    if (!settings.reportUrl || !navigator.onLine) {
      return;
    }
    function send(token) {
      const headers = { 'Content-Type': 'application/json' };
      if (token) {
        headers['X-CSRF-Token'] = token;
      }
      fetch(settings.reportUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({
          deviceId,
          time: Date.now(),
          supported: false,
          reason,
          userAgent: navigator.userAgent,
        }),
      }).catch(() => {});
    }
    // A signed-in report needs a CSRF token first.
    if (drupalSettings.user && String(drupalSettings.user.uid) !== '0') {
      fetch(settings.tokenUrl, { credentials: 'same-origin' })
        .then((response) => (response.ok ? response.text() : null))
        .then((token) => send(token))
        .catch(() => {});
    } else {
      send(null);
    }
  }

  if (!('serviceWorker' in navigator)) {
    Drupal.mukurtuPwa.supported = false;
    reportUnsupported('no_service_worker');
    return;
  }
  Drupal.mukurtuPwa.supported = true;

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
    if (!navigator.onLine) {
      return;
    }
    // Signed-out devices report without a token, so a rebuild that signs
    // everyone out doesn't stop the check-ins.
    const token = await Drupal.mukurtuPwa.token();
    const headers = { 'Content-Type': 'application/json' };
    if (token) {
      headers['X-CSRF-Token'] = token;
    }
    await fetch(settings.reportUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
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
      // A worker that never starts leaves ask() waiting forever.
      await Promise.race([
        Drupal.mukurtuPwa.ask({ type: 'session', uid }),
        new Promise((resolve, reject) => {
          setTimeout(() => reject(new Error('worker_timeout')), 20000);
        }),
      ]);
      reportOpen().catch(() => reportUnsupported('report_failed'));
      Drupal.mukurtuPwa.flush();
    })
    .catch((e) =>
      reportUnsupported(
        e && e.message === 'worker_timeout' ? 'worker_timeout' : 'worker_failed',
      ),
    );

  window.addEventListener('online', () => Drupal.mukurtuPwa.flush());
})(Drupal, drupalSettings);

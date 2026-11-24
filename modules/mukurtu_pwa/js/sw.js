/**
 * @file
 * Service worker for the Mukurtu PWA spike (#400).
 *
 * Served by PwaController::serviceWorker(), which fills in VERSION and
 * CONFIG. Rules:
 * - Pages always come from the network first. A saved copy is only used
 *   when the network fails, and a 403 or 404 from the network deletes it.
 * - Restricted items expire if they go too long without a successful visit.
 * - Private files (/system/files/) are only stored when someone saves an
 *   item, never as a side effect of browsing.
 * - Only responses that really came from Drupal are stored, so a hosting
 *   holding page (such as Tugboat's, served with HTTP 200) is never kept.
 */

const VERSION = '__VERSION__';
const CONFIG = '__CONFIG__';

const SHELL_CACHE = `mukurtu-shell-${VERSION}`;
const RUNTIME_CACHE = 'mukurtu-runtime-v1';
const SAVED_CACHE = 'mukurtu-saved-v1';
const TEST_CACHE = 'mukurtu-testdata-v1';
const PAGES_CACHE = 'mukurtu-pages-v1';
const DAY = 24 * 60 * 60 * 1000;

// IndexedDB: saved items, the upload outbox, app opens and device facts.
function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('mukurtu-pwa', 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      db.createObjectStore('items', { keyPath: 'url' });
      db.createObjectStore('outbox', { keyPath: 'id' });
      db.createObjectStore('opens', { keyPath: 'time' });
      db.createObjectStore('meta', { keyPath: 'key' });
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

function done(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function store(name, mode = 'readonly') {
  const db = await openDb();
  return db.transaction(name, mode).objectStore(name);
}

const dbGet = async (name, key) => done((await store(name)).get(key));
const dbAll = async (name) => done((await store(name)).getAll());
const dbPut = async (name, value) =>
  done((await store(name, 'readwrite')).put(value));
const dbDelete = async (name, key) =>
  done((await store(name, 'readwrite')).delete(key));

async function getMeta(key, fallback = null) {
  const row = await dbGet('meta', key);
  return row ? row.value : fallback;
}

const setMeta = (key, value) => dbPut('meta', { key, value });

// Response checks.
function isDrupal(response) {
  const generator = response.headers.get('x-generator') || '';
  return (
    /drupal/i.test(generator) ||
    response.headers.has('x-drupal-cache') ||
    response.headers.has('x-drupal-dynamic-cache')
  );
}

function isHtml(response) {
  return (response.headers.get('content-type') || '').includes('text/html');
}

function isPrivateFile(url) {
  return url.pathname.startsWith(`${CONFIG.basePath}system/files/`);
}

function isStaticAsset(url) {
  const path = url.pathname.slice(CONFIG.basePath.length);
  return (
    /^(core|modules|profiles|themes|libraries)\//.test(path) ||
    /^sites\/[^/]+\/files\/(css|js)\//.test(path)
  );
}
// Files a saved item can include. Other requests, such as API and AJAX
// calls, go straight to the network without the service worker.
function isFile(url) {
  const path = url.pathname.slice(CONFIG.basePath.length);
  return (
    isStaticAsset(url) ||
    /^sites\/[^/]+\/files\//.test(path) ||
    path.startsWith('pwa-icon/')
  );
}

function isExpired(item) {
  return (
    item.access === 'restricted' &&
    Date.now() - item.savedAt > CONFIG.restrictedMaxAgeDays * DAY
  );
}

// iOS plays audio and video with range requests and needs a 206 back.
async function withRange(request, response) {
  const range = request.headers.get('range');
  if (!range || response.status !== 200) {
    return response;
  }
  const match = /^bytes=(\d*)-(\d*)$/.exec(range.trim());
  if (!match) {
    return response;
  }
  const blob = await response.blob();
  const size = blob.size;
  let start;
  let end;
  if (match[1] === '') {
    start = Math.max(size - Number(match[2]), 0);
    end = size - 1;
  } else {
    start = Number(match[1]);
    end = match[2] === '' ? size - 1 : Math.min(Number(match[2]), size - 1);
  }
  if (start >= size || start > end) {
    return new Response(null, {
      status: 416,
      headers: { 'Content-Range': `bytes */${size}` },
    });
  }
  const headers = new Headers(response.headers);
  headers.set('Content-Range', `bytes ${start}-${end}/${size}`);
  headers.set('Content-Length', String(end - start + 1));
  return new Response(blob.slice(start, end + 1), {
    status: 206,
    statusText: 'Partial Content',
    headers,
  });
}

// Saved items.
async function removeItem(url) {
  const item = await dbGet('items', url);
  if (!item) {
    return;
  }
  await dbDelete('items', url);
  const cache = await caches.open(SAVED_CACHE);
  await cache.delete(url);
  const stillUsed = new Set();
  (await dbAll('items')).forEach((other) =>
    other.assets.forEach((asset) => stillUsed.add(asset)),
  );
  await Promise.all(
    item.assets
      .filter((asset) => !stillUsed.has(asset))
      .map((asset) => cache.delete(asset)),
  );
}

async function saveItem({ url, title, access, assets }) {
  const cache = await caches.open(SAVED_CACHE);
  const page = await fetch(url, { credentials: 'same-origin' });
  if (!page.ok || page.redirected || !isDrupal(page)) {
    throw new Error(`page ${page.status}`);
  }
  await cache.put(url, page);
  let bytes = 0;
  const stored = [];
  // One at a time, so a page with many large files doesn't exhaust memory.
  for (const asset of assets) {
    try {
      const response = await fetch(asset, { credentials: 'same-origin' });
      if (response.ok && !response.redirected && !isHtml(response)) {
        bytes += Number(response.headers.get('content-length')) || 0;
        await cache.put(asset, response);
        stored.push(asset);
      }
    } catch {
      // A missing asset doesn't stop the item being saved.
    }
  }
  await dbPut('items', {
    url,
    title,
    access,
    assets: stored,
    bytes,
    savedAt: Date.now(),
    uid: await getMeta('uid', '0'),
  });
  return { bytes, assets: stored.length };
}

async function listItems() {
  const cache = await caches.open(SAVED_CACHE);
  const items = await dbAll('items');
  const rows = await Promise.all(
    items.map(async (item) => ({
      url: item.url,
      title: item.title,
      access: item.access,
      savedAt: item.savedAt,
      bytes: item.bytes,
      expired: isExpired(item),
      present: Boolean(await cache.match(item.url)),
    })),
  );
  return rows.sort((a, b) => b.savedAt - a.savedAt);
}

// A different person signed in: drop the previous person's restricted items.
async function setSession(uid) {
  const previous = await getMeta('uid');
  if (previous !== null && previous !== uid) {
    const items = await dbAll('items');
    await Promise.all(
      items
        .filter((item) => item.access === 'restricted' && item.uid !== uid)
        .map((item) => removeItem(item.url)),
    );
  }
  await setMeta('uid', uid);
}

// A random version 4 UUID. crypto.randomUUID() needs Safari 15.4, and older
// iPads still run this worker.
function uuid() {
  if (self.crypto.randomUUID) {
    return self.crypto.randomUUID();
  }
  const bytes = self.crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return [8, 12, 16, 20, 32]
    .map((end, i, ends) => hex.slice(i ? ends[i - 1] : 0, end))
    .join('-');
}

// Device facts that survive only as long as this app's storage does.
async function deviceFacts() {
  let deviceId = await getMeta('deviceId');
  let firstSeen = await getMeta('firstSeen');
  if (!deviceId) {
    deviceId = uuid();
    firstSeen = Date.now();
    await setMeta('deviceId', deviceId);
    await setMeta('firstSeen', firstSeen);
  }
  return { deviceId, firstSeen };
}

async function logOpen({ standalone, usage, quota, persisted }) {
  const items = await listItems();
  const row = {
    time: Date.now(),
    standalone: Boolean(standalone),
    itemsTotal: items.length,
    itemsFound: items.filter((item) => item.present).length,
    usage: Number(usage) || 0,
    quota: Number(quota) || 0,
    persisted: Boolean(persisted),
  };
  await dbPut('opens', row);
  const { deviceId, firstSeen } = await deviceFacts();
  return {
    ...row,
    deviceId,
    deviceFirstSeen: firstSeen,
    swVersion: VERSION,
  };
}

// The upload outbox.
async function broadcast(message) {
  const clients = await self.clients.matchAll({ includeUncontrolled: true });
  clients.forEach((client) => client.postMessage(message));
}

async function freshToken() {
  const response = await fetch(`${CONFIG.basePath}session/token`, {
    credentials: 'same-origin',
    cache: 'no-store',
  });
  if (!response.ok || isHtml(response)) {
    throw new Error('network');
  }
  return response.text();
}

// An upload still marked as running after this long was cut off, for
// example because iOS stopped the service worker, so it's tried again.
const STALE_UPLOAD = 2 * 60 * 1000;

function isDue(entry) {
  if (entry.status === 'uploading') {
    return !entry.attemptAt || Date.now() - entry.attemptAt > STALE_UPLOAD;
  }
  return ['waiting', 'failed'].includes(entry.status);
}

// The photo as a Blob. Drafts store its bytes, because Safari can lose the
// contents of a File kept in IndexedDB. Drafts saved before that change
// still hold the File.
function photoOf(entry) {
  return entry.data
    ? new Blob([entry.data], { type: entry.type || 'image/jpeg' })
    : entry.file;
}

async function uploadError(response) {
  try {
    const { error } = await response.json();
    return error ? `HTTP ${response.status} (${error})` : `HTTP ${response.status}`;
  } catch {
    return `HTTP ${response.status}`;
  }
}

async function runFlush() {
  const uid = await getMeta('uid', '0');
  const entries = (await dbAll('outbox')).filter(isDue);
  if (!entries.length) {
    return;
  }
  let token;
  try {
    token = await freshToken();
  } catch {
    throw new Error('network');
  }
  let networkFailed = false;
  for (const entry of entries) {
    if (entry.uid !== uid) {
      // Never upload one person's draft under another person's account.
      entry.status = 'failed';
      entry.error = 'signed in as someone else';
      await dbPut('outbox', entry);
      continue;
    }
    entry.status = 'uploading';
    entry.attemptAt = Date.now();
    await dbPut('outbox', entry);
    await broadcast({ type: 'outboxChanged' });
    const body = new FormData();
    body.append('client_id', entry.id);
    body.append('title', entry.title);
    body.append('created', String(Math.floor(entry.createdAt / 1000)));
    body.append('size', String(entry.size));
    body.append('photo', photoOf(entry), entry.fileName);
    try {
      const response = await fetch(CONFIG.uploadUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': token },
      });
      if (response.ok && isDrupal(response)) {
        entry.status = 'uploaded';
        entry.uploadedAt = Date.now();
        entry.file = null;
        entry.data = null;
      } else if (!isDrupal(response)) {
        // A holding page or proxy error: the site isn't really there.
        entry.status = 'waiting';
        networkFailed = true;
      } else {
        entry.status = 'failed';
        entry.error = await uploadError(response);
      }
    } catch {
      entry.status = 'waiting';
      networkFailed = true;
    }
    await dbPut('outbox', entry);
    await broadcast({ type: 'outboxChanged' });
  }
  if (networkFailed) {
    // Tells Background Sync to try again later.
    throw new Error('network');
  }
}

// One flush at a time, so opening a page during a background sync doesn't
// send the same draft twice.
let flushing = null;

function flushOutbox() {
  if (!flushing) {
    flushing = runFlush().finally(() => {
      flushing = null;
    });
  }
  return flushing;
}

async function addToOutbox({ title, data, mimeType, name }) {
  const entry = {
    id: uuid(),
    title,
    data,
    type: mimeType,
    fileName: name || 'photo.jpg',
    size: data.byteLength,
    uid: await getMeta('uid', '0'),
    createdAt: Date.now(),
    status: 'waiting',
  };
  await dbPut('outbox', entry);
  try {
    // Lets Android upload after the app is closed.
    await self.registration.sync.register('outbox');
  } catch {
    // No Background Sync (iOS, some browsers): pages flush when they open.
    flushOutbox().catch(() => {});
  }
  return { id: entry.id };
}

async function listOutbox() {
  return (await dbAll('outbox'))
    .map((entry) => {
      const rest = { ...entry };
      delete rest.file;
      delete rest.data;
      return rest;
    })
    .sort((a, b) => b.createdAt - a.createdAt);
}

// Test data, to measure how much storage the browser allows.
async function fillTestData(megabytes) {
  const cache = await caches.open(TEST_CACHE);
  const chunk = new Uint8Array(10 * 1024 * 1024);
  const existing = (await cache.keys()).length;
  for (let i = 0; i < megabytes / 10; i += 1) {
    await cache.put(
      `${CONFIG.basePath}__pwa-test-data/${existing + i}`,
      new Response(chunk),
    );
  }
}

// Lifecycle.
self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(SHELL_CACHE);
      try {
        const response = await fetch(CONFIG.offlineUrl, {
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (response.ok && isDrupal(response)) {
          await cache.put(CONFIG.offlineUrl, response.clone());
          const html = await response.text();
          // A plain exec() loop: matchAll() needs Safari 13.
          const pattern = /(?:href|src)="([^"]+\.(?:css|js)(?:\?[^"]*)?)"/g;
          const assets = [];
          let match = pattern.exec(html);
          while (match) {
            const asset = match[1].replace(/&amp;/g, '&');
            if (asset.startsWith('/') && !asset.startsWith('//')) {
              assets.push(asset);
            }
            match = pattern.exec(html);
          }
          await Promise.all(
            assets.map((asset) =>
              fetch(asset)
                .then((r) => (r.ok && !isHtml(r) ? cache.put(asset, r) : null))
                .catch(() => null),
            ),
          );
        }
      } catch {
        // Installed without an offline page; navigations still work online.
      }
      await self.skipWaiting();
    })(),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const names = await caches.keys();
      await Promise.all(
        names
          .filter(
            (name) => name.startsWith('mukurtu-shell-') && name !== SHELL_CACHE,
          )
          .map((name) => caches.delete(name)),
      );
      await self.clients.claim();
    })(),
  );
});

// Requests.
async function handleNavigation(request, url) {
  const key = url.pathname;
  const item = await dbGet('items', key).catch(() => undefined);
  const keepPage = CONFIG.offlinePages.includes(key);
  try {
    const response = await fetch(request);
    if (!isDrupal(response)) {
      throw new Error('not drupal');
    }
    if (keepPage && response.ok && !response.redirected) {
      await (await caches.open(PAGES_CACHE)).put(key, response.clone());
    }
    if (item && (response.status === 403 || response.status === 404)) {
      await removeItem(key);
    } else if (item && response.ok && !response.redirected) {
      const cache = await caches.open(SAVED_CACHE);
      await cache.put(key, response.clone());
      item.savedAt = Date.now();
      await dbPut('items', item);
    }
    return response;
  } catch {
    if (item && !isExpired(item)) {
      const saved = await (await caches.open(SAVED_CACHE)).match(key);
      if (saved) {
        return saved;
      }
    }
    if (keepPage) {
      const page = await (await caches.open(PAGES_CACHE)).match(key);
      if (page) {
        return page;
      }
    }
    const offline = await caches.match(CONFIG.offlineUrl);
    return (
      offline ||
      new Response('Offline', {
        status: 503,
        headers: { 'Content-Type': 'text/plain' },
      })
    );
  }
}

async function handlePrivateFile(request, url) {
  const key = url.pathname + url.search;
  const cache = await caches.open(SAVED_CACHE);
  try {
    const response = await fetch(request);
    if (response.status === 403 || response.status === 404) {
      await cache.delete(key);
    }
    if (isDrupal(response) || !isHtml(response)) {
      return response;
    }
    throw new Error('not drupal');
  } catch {
    const saved = await cache.match(key);
    return saved
      ? withRange(request, saved)
      : new Response('', { status: 504 });
  }
}

async function handleAsset(request, url) {
  const cached =
    (await (await caches.open(SAVED_CACHE)).match(url.pathname + url.search)) ||
    (await caches.match(request, { ignoreVary: true }));
  if (cached) {
    return withRange(request, cached);
  }
  try {
    const response = await fetch(request);
    if (response.ok && isStaticAsset(url) && !isHtml(response)) {
      const runtime = await caches.open(RUNTIME_CACHE);
      await runtime.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('', { status: 504 });
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  if (request.mode === 'navigate') {
    event.respondWith(handleNavigation(request, url));
  } else if (isPrivateFile(url)) {
    event.respondWith(handlePrivateFile(request, url));
  } else if (isFile(url)) {
    event.respondWith(handleAsset(request, url));
  }
});

self.addEventListener('sync', (event) => {
  if (event.tag === 'outbox') {
    event.waitUntil(flushOutbox());
  }
});

const handlers = {
  session: (data) => setSession(String(data.uid)),
  save: (data) => saveItem(data),
  remove: (data) => removeItem(data.url),
  isSaved: async (data) => Boolean(await dbGet('items', data.url)),
  listItems: () => listItems(),
  logOpen: (data) => logOpen(data),
  opens: async () =>
    (await dbAll('opens')).sort((a, b) => b.time - a.time).slice(0, 50),
  version: () => VERSION,
  outboxAdd: (data) => addToOutbox(data),
  outboxList: () => listOutbox(),
  outboxFlush: () => flushOutbox().then(() => true, () => false),
  fillTestData: (data) => fillTestData(Number(data.megabytes) || 100),
  clearTestData: () => caches.delete(TEST_CACHE),
};

self.addEventListener('message', (event) => {
  const port = event.ports[0];
  const handler = event.data && handlers[event.data.type];
  if (!port || !handler) {
    return;
  }
  event.waitUntil(
    Promise.resolve()
      .then(() => handler(event.data))
      .then(
        (result) => port.postMessage({ result }),
        (error) => port.postMessage({ error: String(error && error.message) }),
      ),
  );
});

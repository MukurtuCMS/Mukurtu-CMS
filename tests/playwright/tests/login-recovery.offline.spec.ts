import { expect, test, Page } from '@playwright/test';
import { Login } from '~components/login';

/**
 * Tests for the re-fill step in Login.login().
 *
 * #2267 recorded that login failing intermittently in CI: by the time the
 * button was clicked both fields were empty -- same DOM nodes, marker
 * attribute intact, values gone -- so the browser's own validation blocked
 * the submission and zero HTTP requests were ever sent. Three CI runs of
 * instrumentation never identified what empties them, and it reproduces
 * nowhere else, which is why this drives a form that does it on purpose
 * rather than waiting for CI to do it again.
 *
 * Offline, via page.route(), so it needs no site: see
 * preview-resilience.offline.spec.ts for the same reasoning.
 */

/**
 * A stand-in for Drupal's login form, close enough for the locators.
 *
 * It posts to /user/1 rather than back to /user/login as Drupal's does.
 * Playwright does not intercept the target of a redirect it fulfilled
 * itself, so a 302 answer here would send the browser to the real network
 * and the test would assert against a 404. What this test needs is that a
 * POST is sent at all, and that the URL leaves /user/login afterwards;
 * which path it lands on is incidental to that.
 */
function loginPage(extraScript = ''): string {
  return `<!DOCTYPE html><html lang="en"><head><title>Log in | Mukurtu CMS</title></head>
<body>
  <form id="user-login-form" method="post" action="/user/1">
    <label for="edit-name">Username</label>
    <input id="edit-name" name="name" required>
    <label for="edit-pass">Password</label>
    <input id="edit-pass" name="pass" type="password" required>
    <button type="submit">Log in</button>
  </form>
  <script>${extraScript}</script>
</body></html>`;
}

/** The form as #2267 saw it: filled, then quietly emptied again. */
const WIPES_ITS_OWN_FIELDS = loginPage(`
  setTimeout(function () {
    document.getElementById('edit-name').value = '';
    document.getElementById('edit-pass').value = '';
  }, 1500);
`);

/**
 * Serves the login form and answers its submission.
 *
 * Reports how many POSTs arrived. Zero is the #2267 signature exactly: the
 * click succeeds, no request is ever sent, and Login.login() waits out its
 * full timeout on a redirect that cannot come.
 *
 * Regexes rather than globs, because Playwright's `**\/user/1` does not
 * match `https://host/user/1`, and a route that does not match sends the
 * request to the real network instead.
 */
async function serveLogin(page: Page, body: string): Promise<() => number> {
  let posts = 0;
  await page.route(/\/user\/login$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'text/html', body });
  });
  await page.route(/\/user\/1$/, async (route) => {
    if (route.request().method() === 'POST') {
      posts += 1;
    }
    await route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: '<title>admin | Mukurtu CMS</title>',
    });
  });
  return () => posts;
}

test('submits a login form that wiped its own fields after being filled', async ({ page }) => {
  const posts = await serveLogin(page, WIPES_ITS_OWN_FIELDS);

  await new Login(page).login('admin', 'admin');

  // Without the re-fill, `required` plus two empty fields means the browser
  // blocks the submission, no POST is ever sent, and the login waits out
  // its full 30s on a redirect that cannot come.
  expect(posts()).toBe(1);
  await expect(page).toHaveTitle('admin | Mukurtu CMS');
});

test('negative control: an ordinary login submits once and is not re-filled', async ({ page }) => {
  const posts = await serveLogin(page, loginPage());
  const logged: string[] = [];
  page.on('console', (message) => logged.push(message.text()));

  await new Login(page).login('admin', 'admin');

  expect(posts()).toBe(1);
  // If this fires on a form that was never wiped, the check is too loose
  // and would hide a real failure behind a silent re-fill.
  expect(logged.filter((line) => line.includes('empty again by submit time'))).toEqual([]);
});

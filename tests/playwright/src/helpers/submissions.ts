import { Browser, Page } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Setup/teardown for scanning the public submission form.
 *
 * The submission form ships **disabled** (`status: false`,
 * `access_level: authenticated`), so /submit/node/digital_heritage is a 403
 * on a stock install and a URL-keyed scan would never see it. Rather than
 * skip it -- which reproduces exactly the blind spot that let an unlabeled
 * select survive in the import wizard for months -- the suite turns it on,
 * scans it, and puts the setting back the way it found it.
 *
 * Driven through the admin UI rather than drush: CI runs Playwright from a
 * GitHub runner against a remote Tugboat preview, where there is no local
 * site and no drush to call.
 */

/** The bundle whose submission settings ship with the profile. */
export const SUBMISSION_BUNDLE = 'digital_heritage';

/** Path of the public submission form for that bundle. */
export const SUBMISSION_FORM_PATH = `/submit/node/${SUBMISSION_BUNDLE}`;

/** Path of its thank-you page. */
export const SUBMISSION_THANK_YOU_PATH = `${SUBMISSION_FORM_PATH}/thank-you`;

/** Admin path of the settings entity that gates both. */
const SETTINGS_PATH = `/admin/config/mukurtu/submissions/${SUBMISSION_BUNDLE}`;

/**
 * What the settings looked like before the suite touched them.
 *
 * `null` means the suite changed nothing and teardown should do nothing --
 * either the form was already enabled, or it could not be reached at all.
 */
export type SubmissionFormState = {
  status: boolean;
  accessLevel: string | null;
} | null;

/**
 * Enables the public submission form for anonymous visitors.
 *
 * @param browser
 *   Used to open a throwaway admin context, so the scanning tests keep
 *   whatever session they run under.
 *
 * @returns
 *   The previous state to hand to restoreSubmissionForm(), or null if
 *   nothing was changed.
 */
export async function enableSubmissionForm(browser: Browser): Promise<SubmissionFormState> {
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // adminAccount(), and via the empty-safe helper: an unset GitHub
    // Actions secret arrives as "" rather than undefined, so the previous
    // `process.env.A11Y_USERNAME ?? 'admin'` logged in with an empty
    // username once the workflow started passing the secrets through, and
    // both submission scans failed on the 30s login timeout.
    const admin = adminAccount();
    const login = new Login(page);
    await login.login(admin.username, admin.password);

    const response = await page.goto(SETTINGS_PATH);
    if (!response || !response.ok()) {
      // No permission to administer submissions, or the settings entity
      // isn't there. Leave the site alone; the scans will skip.
      return null;
    }

    const statusCheckbox = page.locator('input[name="status"]');
    if ((await statusCheckbox.count()) === 0) {
      return null;
    }

    const previous: SubmissionFormState = {
      status: await statusCheckbox.isChecked(),
      accessLevel: await page.locator('input[name="access_level"]:checked').getAttribute('value'),
    };

    // Already public: nothing to change, and nothing to put back.
    if (previous.status && previous.accessLevel === 'anonymous') {
      return null;
    }

    await statusCheckbox.check();
    await page.locator('input[name="access_level"][value="anonymous"]').check();
    await page.locator('#edit-submit, input[type="submit"][value="Save"]').first().click();
    await page.waitForLoadState('networkidle');

    return previous;
  }
  finally {
    await context.close();
  }
}

/**
 * Puts the submission settings back as they were.
 *
 * A no-op when enableSubmissionForm() reported it changed nothing.
 */
export async function restoreSubmissionForm(browser: Browser, previous: SubmissionFormState): Promise<void> {
  if (previous === null) {
    return;
  }

  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // adminAccount(), and via the empty-safe helper: an unset GitHub
    // Actions secret arrives as "" rather than undefined, so the previous
    // `process.env.A11Y_USERNAME ?? 'admin'` logged in with an empty
    // username once the workflow started passing the secrets through, and
    // both submission scans failed on the 30s login timeout.
    const admin = adminAccount();
    const login = new Login(page);
    await login.login(admin.username, admin.password);

    const response = await page.goto(SETTINGS_PATH);
    if (!response || !response.ok()) {
      return;
    }

    const statusCheckbox = page.locator('input[name="status"]');
    if (previous.status) {
      await statusCheckbox.check();
    }
    else {
      await statusCheckbox.uncheck();
    }
    if (previous.accessLevel) {
      await page.locator(`input[name="access_level"][value="${previous.accessLevel}"]`).check();
    }
    await page.locator('#edit-submit, input[type="submit"][value="Save"]').first().click();
    await page.waitForLoadState('networkidle');
  }
  finally {
    await context.close();
  }
}

/**
 * Whether the public form is actually reachable anonymously right now.
 *
 * Checks for the form itself, not just a 2xx. Mukurtu serves a themed
 * "Incorrect Permissions" page for denied access, so a status check alone
 * can pass while the thing being scanned is an error page -- which would
 * report a clean result for a form nobody actually scanned, the exact
 * false assurance this whole entry exists to avoid.
 */
export async function submissionFormIsReachable(page: Page): Promise<boolean> {
  const response = await page.goto(SUBMISSION_FORM_PATH);
  if (response === null || !response.ok()) {
    return false;
  }

  // The submission form always renders the target bundle's title field.
  return (await page.locator('form input[name="title[0][value]"]').count()) > 0;
}

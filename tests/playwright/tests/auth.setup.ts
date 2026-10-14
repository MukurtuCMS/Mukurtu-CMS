import { Page, test as setup } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { Login } from '~components/login';
import {
  Account,
  adminAccount,
  managerAccount,
  memberAccount,
} from '~helpers/a11y-credentials';
import { ADMIN_STATE, MANAGER_STATE, MEMBER_STATE } from '~helpers/auth-state';

/**
 * Logs each role in once, for the whole run.
 *
 * Runs as its own project, which every project that needs a session
 * depends on (see playwright.config.ts). Replaces the per-test
 * `beforeEach` login the suite used to do about 66 times a run; see
 * ~helpers/auth-state for the count and the reasoning.
 *
 * The three run in parallel: they are independent accounts, and the point
 * of the exercise is to stop spending minutes on logins.
 *
 * If one of these fails, Playwright skips the projects that depend on it
 * and the run is red. That is the intended trade: a wider blast radius than
 * a single scan's `beforeEach`, in exchange for three chances to hit a
 * preview outage instead of 66. Login.login() goes through gotoReady(), so
 * a preview that is merely on its way back is waited out rather than failed
 * on.
 */
async function signIn(page: Page, account: Account, statePath: string): Promise<void> {
  const login = new Login(page);
  await login.login(account.username, account.password);

  // storageState() writes the file but does not promise to create the
  // directory, and on a fresh checkout playwright/.auth does not exist.
  fs.mkdirSync(path.dirname(statePath), { recursive: true });
  await page.context().storageState({ path: statePath });
}

setup('authenticate as a member', async ({ page }) => {
  await signIn(page, memberAccount(), MEMBER_STATE);
});

setup('authenticate as a community manager', async ({ page }) => {
  await signIn(page, managerAccount(), MANAGER_STATE);
});

setup('authenticate as an administrator', async ({ page }) => {
  await signIn(page, adminAccount(), ADMIN_STATE);
});

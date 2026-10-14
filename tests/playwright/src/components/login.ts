import { Page } from '@playwright/test';
import { drush } from "~helpers/drush";
import { gotoReady } from "~helpers/preview";

export class Login {
  private readonly page: Page;

  public constructor(page: Page) {
    this.page = page;
  }

  public async login(username: string, password: string, setPassword?: boolean): Promise<void> {
    // Change the account password to be the value specified.
    if (setPassword) {
      await drush(`user:password ${username} ${password}`);
    }

    // gotoReady(), not page.goto(): a suspended or resuming Tugboat preview
    // serves Tugboat's own holding page at HTTP 200 with no login form on
    // it, and the fill() below would then time out after 30s with nothing to
    // say about why. See src/helpers/preview.ts.
    const response = await gotoReady(this.page, '/user/login');
    const usernameField = this.page.getByLabel('Username');
    const passwordField = this.page.getByLabel('Password');
    const loginButton = this.page.getByRole('button', { name: 'Log in' });
    // Longer timeouts than the global 5s actionTimeout: the first request
    // against a cold environment (freshly built Tugboat preview) can take
    // much longer to render the form and to process the login submission.
    try {
      await usernameField.fill(username, { timeout: 30000 });
    } catch (error) {
      // Say what was actually served. A bare "waiting for
      // getByLabel('Username')" timeout is indistinguishable between a login
      // form that is slow, a login form that is missing, and a page that was
      // never the login form at all -- which is how eight failures on
      // 2026-09-20 read as application bugs for hours.
      throw new Error(
        `Could not fill the username field on /user/login. The page served `
        + `HTTP ${response?.status() ?? '(no response)'} titled `
        + `"${await this.page.title()}". A page that is not the login form `
        + `points at the environment rather than at the form.\n`
        + `${(error as Error).message}`,
      );
    }
    await passwordField.fill(password);

    // The bot-protection work put an ALTCHA "I'm not a robot" checkbox on
    // user_login_form for every anonymous visitor (which is everyone
    // attempting to log in, by definition) and enabled Honeypot's
    // time-limit check (honeypot.settings:time_limit, 5s by default) on the
    // same form. Without checking the box and waiting out the time floor,
    // every automated login here is silently rejected as a bot.
    const altchaCheckbox = this.page.getByRole('checkbox', { name: /not a robot/i });
    if (await altchaCheckbox.count() > 0) {
      await altchaCheckbox.click();
    }
    await this.page.waitForTimeout(7000);

    // Confirm the form still holds what was typed into it, immediately
    // before submitting. #2267 recorded this login failing intermittently
    // in CI with both fields empty by the time the button was clicked --
    // same DOM nodes, marker attribute intact, values gone -- which native
    // HTML5 validation then blocks, so the click fired zero HTTP requests
    // and the wait below timed out with nothing to show for it. Whatever
    // empties them was never identified (#2281 carries the leads).
    //
    // Re-filling is both the recovery and the evidence: a login that would
    // have failed now succeeds, and the log says it happened, which is more
    // than three CI runs of instrumentation managed to extract.
    if (await usernameField.inputValue() !== username || (await passwordField.inputValue()) === '') {
      console.log(`The login form for "${username}" was empty again by submit time; re-filling. See issue #2281.`);
      await usernameField.fill(username);
      await passwordField.fill(password);
      if (await altchaCheckbox.count() > 0 && !(await altchaCheckbox.isChecked())) {
        await altchaCheckbox.click();
      }

      if (await usernameField.inputValue() !== username) {
        throw new Error(
          `The login form for "${username}" would not keep the values filled `
          + `into it, so submitting it can only be blocked by the browser's `
          + `own validation. See issue #2281.`,
        );
      }
    }

    // Wait for the post-login redirect to complete before returning:
    // clicking the button alone doesn't wait for the resulting navigation,
    // so callers could otherwise navigate away and cancel the login
    // request before the session cookie is ever set.
    await Promise.all([
      this.page.waitForURL((url) => !url.pathname.startsWith('/user/login'), { timeout: 30000 }),
      loginButton.click({ timeout: 30000 }),
    ]);
  }

  public async logout(): Promise<void> {
    await gotoReady(this.page, '/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

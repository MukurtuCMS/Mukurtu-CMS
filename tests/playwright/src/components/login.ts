import { Page } from '@playwright/test';
import { drush } from "~helpers/drush";

export class Login {
  private readonly page: Page;

  public constructor(page: Page) {
    this.page = page;
  }

  // Longer timeouts than the global 5s actionTimeout: the first request
  // against a cold environment (freshly built Tugboat preview) can take
  // much longer to render the form and to process the login submission.
  public async login(username: string, password: string, setPassword?: boolean): Promise<void> {
    // Change the account password to be the value specified.
    if (setPassword) {
      await drush(`user:password ${username} ${password}`);
    }

    await this.page.goto('/user/login');
    const usernameField = this.page.getByLabel('Username');
    const passwordField = this.page.getByLabel('Password');
    const loginButton = this.page.getByRole('button', { name: 'Log in' });
    await usernameField.fill(username, { timeout: 30000 });
    await passwordField.fill(password);

    // The bot-protection work put an ALTCHA "I'm not a robot" checkbox on
    // user_login_form for every anonymous visitor (which is everyone
    // attempting to log in, by definition) and enabled Honeypot's
    // time-limit check (honeypot.settings:time_limit, 5s by default) on the
    // same form. Without checking the box and waiting out the time floor,
    // every automated login here is silently rejected as a bot.
    //
    // ALTCHA solves a client-side proof-of-work challenge after the
    // checkbox is clicked, which normally finishes in well under a second
    // but is CPU-bound: under CI's contention (multiple concurrent browser
    // processes, worst near the tail of a long run) it can occasionally
    // take much longer. A fixed wait long enough for the common case
    // submits an unsolved challenge under contention, which Drupal rejects
    // as a bot with no visible error -- the page just never navigates away
    // from /user/login, indistinguishable from a slow server until you
    // know to look for this (see PR #2264). Wait for the widget's own
    // verified state instead of guessing. Still awaited alongside the 7s
    // honeypot floor, so this never waits less than 7s even when ALTCHA
    // verifies instantly.
    const altchaCheckbox = this.page.getByRole('checkbox', { name: /not a robot/i });
    if (await altchaCheckbox.count() > 0) {
      await altchaCheckbox.click();
      await Promise.all([
        this.page.locator('altcha-widget .altcha[data-state="verified"]').waitFor({ timeout: 60000 }),
        this.page.waitForTimeout(7000),
      ]);
    }
    else {
      await this.page.waitForTimeout(7000);
    }

    // Wait for the post-login redirect to complete before returning:
    // clicking the button alone doesn't wait for the resulting navigation,
    // so callers could otherwise navigate away and cancel the login
    // request before the session cookie is ever set. 45s rather than the
    // 30s used above: PR #2264 observed the login POST itself occasionally
    // taking longer than 30s under CI's concurrent worker load, separately
    // from the ALTCHA timing above.
    await Promise.all([
      this.page.waitForURL((url) => !url.pathname.startsWith('/user/login'), { timeout: 45000 }),
      loginButton.click({ timeout: 45000 }),
    ]);
  }

  public async logout(): Promise<void> {
    await this.page.goto('/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

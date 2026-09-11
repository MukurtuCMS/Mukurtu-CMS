import { Page } from '@playwright/test';
import { drush } from "~helpers/drush";

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

    await this.page.goto('/user/login');
    const usernameField = this.page.getByLabel('Username');
    const passwordField = this.page.getByLabel('Password');
    const loginButton = this.page.getByRole('button', { name: 'Log in' });
    // Longer timeouts than the global 5s actionTimeout: the first request
    // against a cold environment (freshly built Tugboat preview) can take
    // much longer to render the form and to process the login submission.
    await usernameField.fill(username, { timeout: 30000 });
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
    await this.page.goto('/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

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

    // TEMPORARY diagnostic for PR #2264, round 3: the :invalid check from
    // the previous commit showed the username/password fields reporting as
    // empty (required, invalid) at the moment of the failed submit, even
    // though they were filled successfully here moments earlier with no
    // error. That's consistent with the form being replaced/rebuilt
    // in-place sometime between now and the click (a fresh, empty DOM node
    // in the same location), not with the fill() itself failing. Mark the
    // actual DOM node so we can tell after the fact whether it's still the
    // same element. To be removed once the cause is known.
    await this.page.evaluate(() => {
      const el = document.getElementById('edit-name');
      if (el) el.setAttribute('data-diagnostic-marker', 'original-fill');
    });

    // The bot-protection work put an ALTCHA "I'm not a robot" checkbox on
    // user_login_form for every anonymous visitor (which is everyone
    // attempting to log in, by definition) and enabled Honeypot's
    // time-limit check (honeypot.settings:time_limit, 5s by default) on the
    // same form. Without checking the box and waiting out the time floor,
    // every automated login here is silently rejected as a bot.
    //
    // ALTCHA solves a client-side proof-of-work challenge after the
    // checkbox is clicked, then sets its underlying checkbox input's
    // `checked` DOM property via JS. Its own `data-state="verified"`
    // attribute can flip before that property write actually lands under
    // CPU contention (confirmed on PR #2264: a diagnostic showed the
    // login button's click() succeeding but triggering zero network
    // requests -- the browser's native HTML5 validation was silently
    // blocking submission because the checkbox, still `required`, wasn't
    // actually `checked` yet from its own perspective, even though our
    // `data-state` check had already passed). Wait on the checkbox's own
    // `checked` property directly instead of the widget's attribute.
    const altchaCheckbox = this.page.getByRole('checkbox', { name: /not a robot/i });
    if (await altchaCheckbox.count() > 0) {
      await altchaCheckbox.click();
      await Promise.all([
        this.page.waitForFunction(() => {
          const checkbox = document.querySelector('altcha-widget input[type="checkbox"]');
          return checkbox instanceof HTMLInputElement && checkbox.checked;
        }, { timeout: 60000 }),
        this.page.waitForTimeout(7000),
      ]);
    }
    else {
      await this.page.waitForTimeout(7000);
    }

    try {
      // Wait for the post-login redirect to complete before returning:
      // clicking the button alone doesn't wait for the resulting
      // navigation, so callers could otherwise navigate away and cancel
      // the login request before the session cookie is ever set.
      await Promise.all([
        this.page.waitForURL((url) => !url.pathname.startsWith('/user/login'), { timeout: 30000 }),
        loginButton.click({ timeout: 30000 }),
      ]);
    }
    catch (error) {
      const diagnostic = await this.page.evaluate(() => {
        const el = document.getElementById('edit-name') as HTMLInputElement | null;
        return {
          exists: !!el,
          sameNode: el?.getAttribute('data-diagnostic-marker') === 'original-fill',
          currentValue: el?.value ?? null,
        };
      }).catch(() => 'evaluate failed');
      console.error(`[login diagnostic] username field at failure time: ${JSON.stringify(diagnostic)}`);
      throw error;
    }
  }

  public async logout(): Promise<void> {
    await this.page.goto('/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

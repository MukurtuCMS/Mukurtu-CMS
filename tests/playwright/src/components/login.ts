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

    // TEMPORARY diagnostic for PR #2264, round 2: the checked-property fix
    // above didn't resolve it (still times out here), and the previous
    // network-request diagnostic was removed in that same commit, so there
    // is no evidence yet on whether it changed the "zero requests fired"
    // symptom at all. Re-added, plus a direct check for any element the
    // browser's own validation currently considers :invalid, to settle
    // whether this is still a native-validation block or something else.
    // To be removed once the cause is known.
    const netLog: string[] = [];
    const onRequest = (req: import('@playwright/test').Request) => {
      if (req.url().includes('/user/login')) {
        netLog.push(`--> ${req.method()} ${req.url()} @ ${Date.now()}`);
      }
    };
    const onResponse = (res: import('@playwright/test').Response) => {
      if (res.url().includes('/user/login')) {
        netLog.push(`<-- ${res.status()} ${res.url()} @ ${Date.now()}`);
      }
    };
    this.page.on('request', onRequest);
    this.page.on('response', onResponse);

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
      const invalidEls = await this.page.evaluate(() =>
        Array.from(document.querySelectorAll(':invalid')).map((el) => el.outerHTML.slice(0, 300)),
      ).catch(() => ['<evaluate failed>']);
      console.error(
        `[login diagnostic] /user/login network activity:\n${netLog.join('\n') || '(none observed)'}\n` +
        `[login diagnostic] :invalid elements:\n${invalidEls.join('\n') || '(none)'}`,
      );
      throw error;
    }
    finally {
      this.page.off('request', onRequest);
      this.page.off('response', onResponse);
    }
  }

  public async logout(): Promise<void> {
    await this.page.goto('/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

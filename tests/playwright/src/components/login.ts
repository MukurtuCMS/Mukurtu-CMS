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
    await usernameField.fill(username);
    await passwordField.fill(password);
    await loginButton.click();
  }

  public async logout(): Promise<void> {
    await this.page.goto('/user/logout');
    const logOutButton = this.page.getByRole('button', { name: 'Log out' });
    await logOutButton.click();
  }

}

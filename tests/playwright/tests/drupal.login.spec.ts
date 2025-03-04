import { expect, test } from "@playwright/test";
import { drush } from "~helpers/drush";

// This tests proves parallel databases work by setting a random title for the
// first node created in the site.
test('Login test', async ({ page }) => {
  await drush('user:password admin "admin"');

  await page.goto('/user/login');
  const username = page.getByLabel('Username');
  const password = page.getByLabel('Password');
  const loginButton = page.getByRole('button', { name: 'Log in' });
  await username.fill('admin');
  await password.fill('admin');
  await loginButton.click();

  await expect(page.getByRole('link', { name: 'Log out' })).toHaveCount(1);
});

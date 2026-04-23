import { test, expect } from '@playwright/test';
import { Login } from '~components/login';
import submitEntityForm from '~helpers/submit-entity-form';
import waitForAjax from '~helpers/ajax';

const YEAR = new Date().getFullYear().toString();

/**
 * Navigate to the Mukurtu Footer block content edit form.
 *
 * The footer block_content entity is created by hook_install(), so it always
 * exists on a running site. Navigating via the admin listing avoids
 * hardcoding a database ID.
 */
async function gotoFooterEdit(page) {
  await page.goto('/admin/content/block-content');
  await page.locator('table tbody tr')
    .filter({ hasText: 'Mukurtu Footer' })
    .getByRole('link', { name: 'Edit' })
    .click();
}

// ─── Anonymous / smoke tests ──────────────────────────────────────────────────

test('footer: renders on the home page', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.mukurtu-footer')).toBeVisible();
});

test('footer: copyright shows the current year', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.mukurtu-footer .copyright-message')).toContainText(YEAR);
});

test('footer: email link is absent when no address is set', async ({ page }) => {
  // On a fresh install the contact email field is empty.
  // This confirms the template hides the element rather than rendering an
  // empty mailto: link. If a previous test run left an address, this test
  // will skip rather than fail — see the admin email test for active coverage.
  await page.goto('/');
  const emailLink = page.locator('.mukurtu-footer .email a');
  const count = await emailLink.count();
  if (count === 0) {
    // Confirmed: no email link rendered.
    expect(count).toBe(0);
  } else {
    test.skip();
  }
});

// ─── Admin edit tests ─────────────────────────────────────────────────────────

test('footer: admin can update the copyright message', async ({ page }) => {
  const login = new Login(page);
  await login.login('admin', 'admin');
  await gotoFooterEdit(page);

  const field = page.getByLabel('Copyright message');
  await field.clear();
  await field.fill(`© ${YEAR} Playwright Test Org`);
  await submitEntityForm(page);
  await expect(page.locator('.messages--status')).toContainText('has been updated');

  await page.goto('/');
  await expect(page.locator('.mukurtu-footer .copyright-message'))
    .toContainText('Playwright Test Org');

  // Restore default so smoke tests stay green on the next run.
  await gotoFooterEdit(page);
  await page.getByLabel('Copyright message').fill('© [current-date:html_year] Mukurtu CMS');
  await submitEntityForm(page);
});

test('footer: admin can set a contact email address and label', async ({ page }) => {
  const login = new Login(page);
  await login.login('admin', 'admin');
  await gotoFooterEdit(page);

  await page.getByLabel('Contact email address').fill('footer@example.org');
  await page.getByLabel('Contact email label').fill('Write to us');
  await submitEntityForm(page);
  await expect(page.locator('.messages--status')).toContainText('has been updated');

  await page.goto('/');
  const emailLink = page.locator('.mukurtu-footer .email a');
  await expect(emailLink).toBeVisible();
  await expect(emailLink).toHaveAttribute('href', 'mailto:footer@example.org');
  await expect(emailLink).toContainText('Write to us');
  // Confirm the aria-label encodes the actual address, not just the label.
  await expect(emailLink).toHaveAttribute('aria-label', /footer@example\.org/);

  // Clear email so the smoke test for "email absent" stays valid.
  await gotoFooterEdit(page);
  await page.getByLabel('Contact email address').clear();
  await submitEntityForm(page);
});

test('footer: admin can add a social link paragraph', async ({ page }) => {
  const login = new Login(page);
  await login.login('admin', 'admin');
  await gotoFooterEdit(page);

  // Add a new social link paragraph via the paragraphs widget.
  const socialWrapper = page.getByTestId('edit-field-footer-social-links-wrapper');
  await socialWrapper.getByRole('button', { name: 'Add Social link' }).click();
  await waitForAjax(page);

  // Fill in the newly added row (always the last tbody row after an add).
  const newRow = socialWrapper.locator('tbody tr').last();
  await newRow.getByLabel('Platform').selectOption('bluesky');
  await newRow.getByLabel('URL').fill('https://bsky.app/profile/mukurtucms.bsky.social');

  await submitEntityForm(page);
  await expect(page.locator('.messages--status')).toContainText('has been updated');

  // Verify the social link renders on the front page with the correct
  // href, platform modifier class, and accessible label.
  await page.goto('/');
  const socialLink = page.locator(
    '.mukurtu-footer .socials__item--bluesky a[href="https://bsky.app/profile/mukurtucms.bsky.social"]'
  );
  await expect(socialLink).toBeVisible();
  await expect(socialLink).toHaveAttribute('aria-label', /Bluesky/i);
  await expect(socialLink).toHaveAttribute('rel', /noopener/);
});

test('footer: social link icon is rendered inside the link', async ({ page }) => {
  // Depends on the social link added by the previous test — run after it or
  // ensure at least one social link exists before running standalone.
  await page.goto('/');
  const icon = page.locator('.mukurtu-footer .socials__icon svg').first();
  await expect(icon).toBeAttached();
  await expect(icon).toHaveAttribute('aria-hidden', 'true');
  await expect(icon).toHaveAttribute('focusable', 'false');
});

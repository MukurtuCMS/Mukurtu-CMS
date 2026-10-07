import { test, expect } from '@playwright/test';

/**
 * WCAG 1.3.5 Identify Input Purpose: inputs collecting information about the
 * user must carry the matching `autocomplete` token.
 *
 * The inventory-wide version of this is report-only (checkInputPurpose in
 * automated-checks.ts), and a report-only check is not evidence — it cannot
 * fail a build, so a regression in it is silent. This gates the one form every
 * site has and every user meets.
 *
 * It asserts the field exists before asserting its token, so renaming the
 * field fails the test rather than quietly passing it with nothing to check.
 */
const USER_INFO_FIELDS: { selector: string; label: string; expected: string[] }[] = [
  { selector: 'form input[name="name"]', label: 'username', expected: ['username'] },
  { selector: 'form input[name="pass"]', label: 'password', expected: ['current-password'] },
];

for (const field of USER_INFO_FIELDS) {
  test(`Input purpose: the login ${field.label} field identifies its purpose`, async ({ page }) => {
    await page.goto('/user/login');
    const input = page.locator(field.selector).first();

    // Without this the test would pass vacuously if the field were renamed.
    await expect(input, `the ${field.label} field is present on /user/login`).toBeVisible();

    const autocomplete = await input.getAttribute('autocomplete');
    expect(
      field.expected,
      `${field.label} field has autocomplete="${autocomplete}", expected one of ${field.expected.join(', ')}`
    ).toContain(autocomplete);
  });
}

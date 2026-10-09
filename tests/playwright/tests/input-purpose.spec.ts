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

/**
 * The account form is the other place a person enters their own details, and
 * it is where core actively works against the criterion: AccountForm sets
 * autocomplete="off" on username, email and password whenever the form is
 * not a registration, and on current password always. mukurtu_core restores
 * the proper tokens when someone is editing their *own* account (#2372),
 * leaving core's behaviour alone when an administrator edits someone else.
 *
 * Gated rather than left to the report-only check, because this is a
 * deliberate override of upstream behaviour: a core update that changes
 * AccountForm, or a refactor that drops the alter, should fail a build
 * rather than quietly restore autocomplete="off".
 */
import { ADMIN_STATE } from '~helpers/auth-state';

test.describe('Input purpose: the account form', () => {
  test.use({ storageState: ADMIN_STATE });

  const SELF_EDIT_FIELDS: { selector: string; label: string; expected: string }[] = [
    { selector: 'form input[name="name"]', label: 'username', expected: 'username' },
    { selector: 'form input[name="mail"]', label: 'email', expected: 'email' },
    { selector: 'form input[name="current_pass"]', label: 'current password', expected: 'current-password' },
  ];

  for (const field of SELF_EDIT_FIELDS) {
    test(`Input purpose: the ${field.label} field identifies its purpose when editing your own account`, async ({ page }) => {
      // The signed-in account's own edit form, found from the account page
      // rather than a hardcoded uid, which differs between environments.
      await page.goto('/user');
      await page.waitForURL(/\/user\/\d+/);
      const uid = page.url().match(/\/user\/(\d+)/)?.[1];
      expect(uid, 'resolved the signed-in account id').toBeTruthy();
      await page.goto(`/user/${uid}/edit`);

      const input = page.locator(field.selector).first();
      // Without this the test would pass vacuously if the field moved.
      await expect(input, `the ${field.label} field is present`).toBeVisible();

      const autocomplete = await input.getAttribute('autocomplete');
      expect(
        autocomplete,
        `${field.label} has autocomplete="${autocomplete}", expected "${field.expected}" (core sets "off" here; mukurtu_core should override it for self-editing)`
      ).toBe(field.expected);
    });
  }
});

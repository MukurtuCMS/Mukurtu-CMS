import { test } from '@playwright/test';
test('auth', async ({ page, context }) => {
  await page.goto(process.env.ULI!); await page.waitForLoadState('networkidle');
  await context.storageState({ path: 'playwright/.auth/admin.json' });
});

import { defineConfig, devices } from '@playwright/test';

/**
 * Standalone config for the manual-audit prep tool.
 *
 * Deliberately outside tests/, which CI runs wholesale: this is an operator
 * tool that drives a site and writes Markdown, not a test, and it must never
 * run in a pipeline. See docs/accessibility/manual-audit-runbook.md.
 */
export default defineConfig({
  testDir: '.',
  testMatch: 'prep.spec.ts',
  // No limit: an operator tool walking a dozen pages, some of them heavy
  // admin forms, should not be cut off part-way through.
  timeout: 0,
  reporter: [['line']],
  use: {
    ...devices['Desktop Chrome'],
    viewport: { width: 1280, height: 900 },
    ignoreHTTPSErrors: true,
    baseURL: process.env.AUDIT_BASE_URL,
  },
});

import { defineConfig, devices } from '@playwright/test';
import * as os from "os";

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require('dotenv').config({
  path: '../../.env',
});

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests',
  /* Maximum time one test can run for. */
  // This is bumped to 140s to give tests time to install and clean up after.
  // Take into account that safari automation is slow too.
  timeout: 60_000,
  expect: {
    /**
     * Maximum time expect() should wait for the condition to be met.
     * For example in `await expect(locator).toHaveText();`
     */
    timeout: 5000,
    // Allow a small number of pixels to change. By not using a ratio, we
    // ensure that large screenshots don't accidentally pass tests. This number
    // comes from a test where subpixel aliasing was affecting a single
    // character. If needed, we could probably increase this to 30 or 50
    // pixels, but more, and we are likely to miss regressions.
    // For reference, removing a short menu local task like "Usage" is
    // around 150 pixels, so we should probably never increase maxDiffPixels
    // beyond 100.
    toHaveScreenshot: {
      maxDiffPixels: 12,
      // We increase this from the default 0.2 to be more flexible with image
      // comparisons too. While we replace the wildrose currently, we've also
      // seen failures on the Iowa.gov logo which we really would like to keep.
      // A good way to test this is to uninstall quick_node_clone locally,
      // export configuration, and run task test:playwright:visualdiff. It
      // should fail due to the missing menu item, but still pass without
      // false failures when the module is re-enabled.
      //
      // We also increase this for specific browsers in
      // takeAccessibleScreenshot().
      //
      // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-2
      threshold: 0.3,
    },
  },
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: (() => {
    if (process.env.CI) {
      // Playwright maintainers recommend 2 CPUs per worker.
      // https://github.com/microsoft/playwright/issues/26739#issuecomment-1699661246
      return os.cpus().length / 2;
    }

    return Math.max(1, os.cpus().length - 2);
  })(),
  /* Stop early on a failure to save CI time. */
  /* Retries that then pass still count as failures.
  /* https://github.com/microsoft/playwright/discussions/20320 */
  maxFailures: process.env.CI ? 10 : 0,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: (() => {
    // The default dot reporter on CI only renders dots when tailing GitHub
    // actions logs when a new line is rendered. This makes it hard to tell if
    // a test has hung. Instead, default to line on CI and html + list
    // everywhere else.
    // https://playwright.dev/docs/test-reporters#reporters-on-ci
    return process.env.CI ? [['line'], ['blob']] : [
      [
        'html', {
        // open: 'never',
        host: '0.0.0.0',
        port: 9323,
      }
      ],
      ['list'],
    ];
  })(),
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    ignoreHTTPSErrors: true,
    /* Maximum time each action such as `click()` can take. Defaults to 0 (no limit). */
    actionTimeout: 0,
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'https://mukurtu4.ddev.site',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'npm run start',
  //   url: 'http://127.0.0.1:3000',
  //   reuseExistingServer: !process.env.CI,
  // },
});

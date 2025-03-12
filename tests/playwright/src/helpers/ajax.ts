import { Page, expect, test } from "@playwright/test";

/**
 * Wait for all ajax requests to finish.
 *
 * @see \Drupal\FunctionalJavascriptTests\JSWebAssert::assertWaitOnAjaxRequest()
 *
 * @param page
 */
export default async function waitForAjax(page) {
  await expect(async () => {
    expect(await page.evaluate(() => {
      return (function () {
        function isAjaxing(instance) {
          return instance && instance.ajaxing === true;
        }

        // Assert no AJAX request is running (via jQuery or Drupal) and no
        // animation is running.
        return (
          // We use ts-ignore because @types/jquery doesn't have active defined,
          // and there is no existing Drupal type library.
          // @ts-expect-error TS2304
          (typeof jQuery === 'undefined' || (jQuery.active === 0 && jQuery(':animated').length === 0)) &&
          // @ts-expect-error TS2304
          (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
        );
      }())
    })).toBeTruthy();
  }).toPass({timeout: 5_000});
}

/**
 * Skip test if the Embed doesn't load.
 *
 * @async
 * @function waitForEmbed
 * @param {string} embedSelector - The Embed selector.
 * @param {string} embedEvent - The Embed event.
 * @param {number} embedTimeout - The Embed timeout.
 * @param {Page} page - The page.
 * @returns {Promise<void>} Nothing to return.
 */
export async function waitForEmbed(embedSelector: string, embedEvent: string, embedTimeout: number, page: Page) {
  // The Embed locator.
  const embedLocator = page.locator(embedSelector);

  // The Embed info.
  const embedInfo = { embedEvent, embedTimeout };

  // Add a timeout to any existing promise.
  const load = await embedLocator.evaluate((embed, embedInfo) => {
    const withTimeout = (millis, promise) => {
      const timeout = new Promise((resolve, reject) =>
        setTimeout(
          () => resolve(false),
          millis));
      return Promise.race([
        promise,
        timeout
      ]);
    };

    return withTimeout(embedInfo.embedTimeout, new Promise<boolean>((resolve) => {
      embed.addEventListener(embedInfo.embedEvent, () => resolve(true), { once: true });
    }));
  }, embedInfo);

  // Verify if the Embed loaded successfully.
  if (!load) {
    test.skip();
  }
}

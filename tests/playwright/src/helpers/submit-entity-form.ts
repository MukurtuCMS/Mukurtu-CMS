import {Page} from "@playwright/test";

/**
 * Submit an entity form on the given page.
 *
 * @param page
 * @param buttonText
 */
export default async function submitEntityForm(page, buttonText = 'Save') {
  // The Gin theme makes multiple Save buttons, select the visible one.
  const actionsWrapper = page.locator('[data-drupal-selector="edit-gin-sticky-actions"]');
  await actionsWrapper.getByRole('button', { name: buttonText, exact: true }).click();
}

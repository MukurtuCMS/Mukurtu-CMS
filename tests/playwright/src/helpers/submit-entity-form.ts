import {Page} from "@playwright/test";

/**
 * Submit an entity form on the given page.
 *
 * @param page
 */
export default async function submitEntityForm(page) {
  // The Gin theme makes multiple Save buttons, select the visible one.
  const actionsWrapper = page.locator('[data-drupal-selector="edit-gin-sticky-actions"]');
  await actionsWrapper.getByRole('button', { name: 'Save', exact: true }).click();
}

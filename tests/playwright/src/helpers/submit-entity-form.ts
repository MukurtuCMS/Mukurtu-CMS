import {Page} from "@playwright/test";

/**
 * Submit an entity form on the given page.
 *
 * @param page
 * @param buttonText
 */
export default async function submitEntityForm(page, buttonText = 'Save') {
  // The Gin theme makes multiple Save buttons, select the visible one.
  const actionsWrapper = page.locator('[data-drupal-selector="edit-gin-sticky-actions"], [data-drupal-selector="gin-sticky-edit-submit"]').first();
  // Entity saves can legitimately exceed the global 5s actionTimeout on
  // cold/shared environments (e.g. a freshly built Tugboat preview) —
  // community creation also builds groups, roles, and protocols.
  await actionsWrapper.getByRole('button', { name: buttonText, exact: true }).click({ timeout: 30000 });
}

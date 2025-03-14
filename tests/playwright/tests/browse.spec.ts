import { test, expect, Page } from "@playwright/test";
import { Login } from "~components/login";
import { LogMessage } from "~components/log-message";
import { communityContent, CommunityForm } from "~pages/community-form";
import waitForAjax from "~helpers/ajax";

/**
 * Check the browsing experience (Grid/List/Map) for Digital Heritage items.
 */
test('Browse tests - Digital Heritage', async ({ page, browserName }) => {
  await page.goto('/digital-heritage');
  await page.getByText('Grid', { exact: true }).click();
  await page.getByText('List', { exact: true }).click();
  await page.getByText('Map', { exact: true }).click();

});

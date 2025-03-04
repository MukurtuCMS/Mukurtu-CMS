import { test, expect, Page } from "@playwright/test";
import { Login } from "~components/login";
import { LogMessage } from "~components/log-message";
import { communityContent, CommunityForm } from "~pages/community-form";
import waitForAjax from "~helpers/ajax";

const requiredContent: communityContent = {
  communityName: "Example community",
  accessMode: "strict",
};

const optionalContent: communityContent = {
  description: "Description of the community.",
  communityManagers: [],
  communityMembers: [],
  communityAffiliates: [],
}

test.beforeEach(async ({ page }) => {
  const login = new Login(page);
  // Log in.
  await login.login('admin', 'admin');
  // await expect(page.getByRole('link', { name: 'Log out' })).toHaveCount(1);
  //
  // const clearLogs = new LogMessage(page);
  // await clearLogs.clearLogs();
  // await page.waitForSelector('.view-empty', { state: 'visible' });
  // await expect(page.locator('.view-empty')).toContainText('No log messages available');
});

test('CRUD tests - Collaborator Page', async ({ page, browserName }) => {
  //test.slow(browserName == 'webkit', 'This test is slow on safari');
  const nid = await fillOnlyRequiredFields(page);

  const login = new Login(page);
  await login.logout();

  // Verify that the page load properly as anonymous.
  // @todo here we can add the other fields to verify.
  await page.goto('/about/contributors-partners');
  await expect(page.locator('a[href="' + requiredContent.communityUrl + '"]').first()).toContainText(requiredContent.organizationName);

  // Log in to Delete the Collaborator.
  await login.login('admin', 'admin');
  await page.goto('/node/' + nid + '/delete')
  await page.getByRole('button', { name: 'Delete', exact: true }).click();
  await expect(page.getByRole('contentinfo', { name: 'Status message' })).toHaveClass(/messages--status/);
  await expect(page).toHaveTitle(/State Climate Policy Dashboard/);
});

test.afterEach(async ({ page }) => {
  //  Verify the errors in the logs
  const errorLogs = new LogMessage(page);
  await errorLogs.validateLogs();
  await expect(page.locator('.view-empty')).toContainText('No log messages available');
});

async function fillOnlyRequiredFields(page: Page) {
  const communityPageForm = new CommunityForm(page);

  // We create Collaborator page.
  await communityPageForm.newContent(page, requiredContent);

  // Verify that the community was created and we are redirected to create the
  // first protocol for the community.
  await expect(page.locator('h1')).toHaveText(`Creating cultural protocol for ${requiredContent.communityName}`);

  // We edit the Collaborator page.
  await page.getByRole('link', { name: 'Edit', exact: true }).click();
  await expect(page).toHaveTitle(/Edit Collaborator/);
  // Get the nid from the edit URL.
  const nid= page.url().match(/\/node\/(\d+)\/edit/)[1];
  await editCollaboratorPage(page, communityPageForm);

  return nid;
}

async function editCollaboratorPage(page: Page, communityPageForm: CommunityForm) {
  await communityPageForm.summary.fillBody(optionalContent.summary);

  // Upload a new image.
  const [fileChooser] = await Promise.all([
    communityPageForm.page.waitForEvent('filechooser'),
    communityPageForm.page.click('[data-drupal-selector="edit-field-collab-logo-0-upload"]')
  ]);

  await fileChooser.setFiles(optionalContent.logo);
  await waitForAjax(communityPageForm.page);
  await communityPageForm.logoAlt.click();
  await communityPageForm.logoAlt.fill(optionalContent.logoAlt);


  await communityPageForm.saveBtn.click();

  // Verify that the content is being displayed properly.
  await expect(page.getByRole('contentinfo', { name: 'Status message' })).toHaveClass(/messages--status/);
}

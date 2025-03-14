import { test, expect, Page } from '@playwright/test';
import { Login } from '~components/login';
import { LogMessage } from '~components/log-message';
import { communityContent, CommunityForm } from '~pages/community-form';
import waitForAjax from '~helpers/ajax';

const defaultContentSpec = {
  // Community entities.
  community: [],
  // Protocol entities. Note can be created as part of the community items.
  protocol: [],
  // Language taxonomy terms.
  language: [],
  // Digital heritage nodes.
  dh: [],
};

/* Define default Community content. */
defaultContentSpec.community.push({
  name: 'First community',
  field_access_mode: 'strict',
  // These protocols are created on the follow-up page after creating a
  // community. Always associated only with the newly created community.
  protocols: [
    {
      name: 'First community protocol 1',
      field_access_mode: 'strict',
    },
    {
      name: 'First community protocol 2',
      field_access_mode: 'strict',
    },
  ],
});

defaultContentSpec.community.push({
  name: 'Second community',
  field_access_mode: 'strict',
  protocols: [
    {
      name: 'Second community protocol 1',
      field_access_mode: 'strict',
    },
    {
      name: 'Second community protocol 2',
      field_access_mode: 'strict',
    },
  ],
});


/* Define additional protocols. */
defaultContentSpec.protocol.push({
  name: 'Shared protocol',
  field_access_mode: 'open',
  // This entity reference field is matched by the community name.
  field_communities: ['First community', 'Second community'],
});

/* Default default Language terms. */
defaultContentSpec.language.push({
  name: 'First language',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1']
});
defaultContentSpec.language.push({
  name: 'Second language',
});

/**
 * Setup tasks run before each test.
 */
test.beforeEach(async ({ page }) => {
  const login = new Login(page);
  await login.login('admin', 'admin');
});

/**
 * Initialize default community content.
 */
test('Default Content: Community', async ({ page, browserName }) => {
  // Loop through all communities and create each one.
  for (const community of defaultContentSpec.community) {
    // Create a community.
    await page.goto('/communities/community/add');
    await page.getByRole('textbox', { name: 'Community name' }).fill(community.name);
    await page
      .getByRole('group', { name: 'Sharing Protocol' })
      .getByRole('radio', { name: community.field_access_mode })
      .check();
    await page.getByRole('button', { name: 'Create Community' }).click();

    // After creating the community, ensure we are redirected to create a
    // protocol associated with this community.
    await expect(page.locator('.page-title')).toContainText(`Creating cultural protocol for ${community.name}`);
    const communityIdMatch = page.url().match(/\/protocols\/protocol\/add\/community\/(\d+)/);
    if (!communityIdMatch) {
      throw new Error('Could not extract created community ID from URL.');
    }
    const communityId = communityIdMatch[1];

    // Loop through all protocols to be created directly against this community.
    for (const protocol of community.protocols) {
      await page.getByRole('textbox', { name: 'Protocol Name' }).fill(protocol.name);
      await page
        .getByRole('group', { name: 'Sharing Protocol' })
        .getByRole('radio', { name: protocol.field_access_mode })
        .check();
      await page.getByRole('button', { name: 'Save and Create Another Protocol' }).click();
      await expect(page.locator('.messages--status')).toContainText(`Created ${protocol.name}.`);
    }
  }
});

/**
 * Initialize default community content.
 */
test('Default Content: Digital Heritage', async ({ page, browserName }) => {
  // Loop through all Digital Heritage items and create each one.
  for (const dh of defaultContentSpec.dh) {
    // Create through the custom dashboard URL.
    await page.goto('/dashboard/node/add/digital_heritage');
    await page.getByRole('textbox', { name: 'Title' }).fill(dh.title);
    await page.getByRole('textbox', { name: 'Summary' }).fill(dh.summary);
    await page
      .getByRole('group', { name: 'Sharing Setting' })
      .getByRole('radio', { name: dh.field_cultural_protocols__sharing })
      .check();

    // Loop through the cultural protocols to be added to this DH item.
    for (const protocol of dh.field_cultural_protocols__value) {
      await page
        .getByRole('group', { name: 'Select cultural protocols to apply to the item' })
        .getByRole('checkbox', { name: protocol})
        .check();
    }

    await page.getByRole('button', { name: 'Save', exact: true }).click();
  }
});

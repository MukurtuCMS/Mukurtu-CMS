import { test, expect, Page } from '@playwright/test';
import { Login } from '~components/login';
import { Ckeditor5 } from "~components/ckeditor5";
import { LogMessage } from '~components/log-message';
import waitForAjax from '~helpers/ajax';

const defaultContentSpec = {
  // Community entities.
  community: [],
  // Protocol entities. Note can be created as part of the community items.
  protocol: [],
  // Category taxonomy terms.
  category: [],
  // Language taxonomy terms.
  language: [],
  // Person nodes.
  person: [],
  // Digital heritage nodes.
  dh: [],
  // Dictionary words.
  word: [],
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

/* Define default Category terms. */
defaultContentSpec.category.push({
  name: 'First category',
  description__value: '<p>This is a description for the first category.</p>',
  description__format: 'basic_html',
});
defaultContentSpec.category.push({
  name: 'Second category',
  description__value: '<p>This is a description for the second category.</p>',
  description__format: 'basic_html',
});

/* Define default Language terms. */
defaultContentSpec.language.push({
  name: 'First language',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1']
});
defaultContentSpec.language.push({
  name: 'Second language',
});

/* Define default Person nodes. */
defaultContentSpec.person.push({
  name: 'Person A',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1'],
  field_date_born__year: '1982',
  field_date_born__month: '9',
  field_date_born__day: '30',
});
defaultContentSpec.person.push({
  name: 'Person B',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1'],
  field_date_born__year: '1920',
  field_date_born__month: '2',
  field_date_born__day: '19',
  field_date_died__year: '2001',
  field_date_died__month: '9',
  field_date_died__day: '30',
  field_deceased: true,
});

/* Define default dictionary word taxonomy terms. */
defaultContentSpec.word.push({
  term: 'Word A',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1'],
  field_dictionary_word_language: 'First language',
  field_alternate_spelling: 'woard A',
  field_definition: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed euismod eget dolor at gravida. Nulla luctus ultricies mi eget dapibus. Duis vitae luctus nunc, id tincidunt ante. Nulla enim quam, dignissim at velit ut, mollis mollis lorem. Aliquam erat volutpat. Vivamus dignissim arcu at risus gravida, et laoreet ex blandit.',
  field_sample_sentences: [
    {
      field_sentence: 'Word A Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    }
  ],
});
defaultContentSpec.person.push({
  name: 'Word B',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['First community protocol 1', 'Second community protocol 1'],
  field_dictionary_word_language: 'Second language',
  field_alternate_spelling: 'werd B',
  field_definition: 'Aliquam erat volutpat. Aliquam erat volutpat. Phasellus vel elit at diam pulvinar tincidunt ac at mi. Mauris faucibus ultrices elit eget imperdiet. Sed sodales leo non ipsum porta blandit. Maecenas porta mauris ac lacinia tempor. Donec condimentum massa vel neque dapibus, id tristique metus consequat.',
  field_sample_sentences: [
    {
      field_sentence: 'Word B Phasellus vel elit at diam pulvinar tincidunt ac at mi.',
    }
  ],
});

/**
 * Global to store if any test content exists yet.
 *
 * This value is set on the first test, and then checked on all subsequent ones.
 */
let testContentExists = null;

/**
 * Setup tasks run before each test.
 */
test.beforeEach(async ({ page }) => {
  const login = new Login(page);
  await login.login('admin', 'admin');

  // Check if default content already exists, and if so, skip recreation.
  if (testContentExists === null) {
    await page.goto('/dashboard');
    const getStartedVisible = await page.locator('.mukurtu-getting-started-communities').isVisible();
    testContentExists = (getStartedVisible === false);
  }
  test.skip(testContentExists === true, 'Content already exists within the database, skipping the default content creation. To create default content, empty all existing content by running delete-content.spec.ts.');
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
    // const communityId = communityIdMatch[1];

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
 * Initialize default category content.
 */
test('Default Content: Category', async ({ page, browserName }) => {
  // Loop through all Digital Heritage items and create each one.
  for (const category of defaultContentSpec.category) {
    // Create through the custom dashboard URL.
    await page.goto('/dashboard/categories');

    // Expand the Details element to populate a new category value.
    await page.getByRole('button', { name: 'Add a new category' }).click();

    await page.getByRole('textbox', { name: 'Category name' }).fill(category.name);

    // Fill summary and body using CKEditor 5
    const summary = new Ckeditor5(page, '[data-drupal-selector="edit-description-wrapper"]');
    await summary.fill(category.description__value);
    await page.getByLabel('Text format').selectOption(category.description__format);

    await page
      // There are two Save buttons on this page, make sure to get just the
      // edit term form and not the term order form.
      .locator('[data-drupal-selector="taxonomy-term-category-form"]')
      .getByRole('button', { name: 'Save', exact: true }).click();
  }
});

/**
 * Initialize default person content.
 */
test('Default Content: Person', async ({ page, browserName }) => {
  // Loop through all Person items and create each one.
  for (const person of defaultContentSpec.person) {
    // Create through the custom dashboard URL.
    await page.goto('/dashboard/node/add/person');
    await page.getByRole('textbox', { name: 'Name' }).fill(person.name);
    await page
      .getByRole('group', { name: 'Sharing Setting' })
      .getByRole('radio', { name: person.field_cultural_protocols__sharing })
      .check();

    // Loop through the cultural protocols to be added to this Person item.
    for (const protocol of person.field_cultural_protocols__value) {
      await page
        .getByRole('group', { name: 'Cultural Protocols' })
        .getByRole('checkbox', { name: protocol})
        .check();
    }

    if (person.field_date_born__year) {
      await page
        .getByRole('group', { name: 'Date Born' })
        .getByLabel('Year').fill(person.field_date_born__year);
      await page
        .getByRole('group', { name: 'Date Born' })
        .getByLabel('Month').selectOption(person.field_date_born__month);
      await page
        .getByRole('group', { name: 'Date Born' })
        .getByLabel('Day').selectOption(person.field_date_born__day);
    }

    if (person.field_date_died__year) {
      await page
        .getByRole('group', { name: 'Date Died' })
        .getByLabel('Year').fill(person.field_date_died__year);
      await page
        .getByRole('group', { name: 'Date Died' })
        .getByLabel('Month').selectOption(person.field_date_died__month);
      await page
        .getByRole('group', { name: 'Date Died' })
        .getByLabel('Day').selectOption(person.field_date_died__day);
    }

    if (person.field_deceased) {
      await page.getByRole('checkbox', { name: 'Deceased' }).check();
    }

    await page.getByRole('button', { name: 'Save', exact: true }).click();
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

/**
 * Initialize default dictionary content.
 */
test('Default Content: Dictionary Word', async ({ page, browserName }) => {
  // Loop through all Digital Heritage items and create each one.
  for (const word of defaultContentSpec.word) {
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

import { test, expect, Page } from '@playwright/test';
import path = require("path");
import { Login } from '~components/login';
import { Ckeditor5 } from "~components/ckeditor5";
import submitEntityForm from '~helpers/submit-entity-form';
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
  // Collection nodes.
  collection: [],
  // Dictionary words.
  word: [],
};

/* Define default Community content. */
defaultContentSpec.community.push({
  name: 'Tribal community',
  field_access_mode: 'Community only',
  // These protocols are created on the follow-up page after creating a
  // community. Always associated only with the newly created community.
  protocols: [
    {
      name: 'Tribal members only',
      field_access_mode: 'Strict',
    },
    {
      name: 'Tribal community public access',
      field_access_mode: 'Open',
    },
  ],
});
defaultContentSpec.community.push({
  name: 'Repository community',
  // Public, unlike Tribal community above, so that /communities lists at
  // least one community to anonymous visitors. The accessibility scans
  // discover the community page from that listing; with every community
  // "Community only" the anonymous discovery had nothing to find.
  field_access_mode: 'Public',
  protocols: [
    {
      name: 'Repository under review',
      field_access_mode: 'Strict',
    },
    {
      name: 'Repository public access',
      field_access_mode: 'Open',
    },
  ],
});


/* Define additional protocols. */
defaultContentSpec.protocol.push({
  name: 'Shared protocol',
  field_access_mode: 'Open',
  // This entity reference field is matched by the community name.
  field_communities: ['Tribal community', 'Repository community'],
});

/* Define default Category terms. */
defaultContentSpec.category.push({
  name: 'Education',
  description__value: '<p>This is a description for the Education category.</p>',
  description__format: 'basic_html',
});
defaultContentSpec.category.push({
  name: 'Government to government relations',
  description__value: '<p>This is a description for the Government to government relations category.</p>',
  description__format: 'basic_html',
});

/* Define default Language terms. */
defaultContentSpec.language.push({
  name: 'First language',
});
defaultContentSpec.language.push({
  name: 'Second language',
});

/* Define default Person nodes. */
defaultContentSpec.person.push({
  name: 'Person A',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access', 'Repository under review'],
  field_date_born__year: '1982',
  field_date_born__month: '9',
  field_date_born__day: '30',
});
defaultContentSpec.person.push({
  name: 'Person B',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access', 'Repository under review'],
  field_date_born__year: '1920',
  field_date_born__month: '2',
  field_date_born__day: '19',
  field_date_died__year: '2001',
  field_date_died__month: '9',
  field_date_died__day: '30',
  field_deceased: true,
});

/* Define default Digital Heritage nodes. */
// Until these existed the array above was declared and never filled, so the
// "Default Content: Digital Heritage" test looped over nothing, passed, and
// /digital-heritage said "No results" on every preview. Every discovery scan
// that starts from that listing skipped as a consequence. See issue #2250.
//
// One public and one members-only, deliberately: the anonymous scans need
// something they can reach, and the member scans need something the
// anonymous scans cannot, or the two are indistinguishable.
defaultContentSpec.dh.push({
  title: 'Public digital heritage item',
  summary: 'Shared under an open protocol, so it is visible to everyone including visitors who are not logged in.',
  field_category: ['Education'],
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access'],
});
defaultContentSpec.dh.push({
  title: 'Members-only digital heritage item',
  summary: 'Shared under a strict protocol, so it is visible only to members of that protocol.',
  field_category: ['Government to government relations'],
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal members only'],
});

/* Define default Collection nodes. */
// Nothing seeded collections before. Same reasoning as the digital heritage
// items above; in addition, /collections returns 403 rather than an empty
// page when the browse view is empty, so with no collections the anonymous
// collections-browse scan was auditing an error page.
defaultContentSpec.collection.push({
  title: 'Public collection',
  summary: 'A collection shared under an open protocol, visible to everyone.',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access'],
});
defaultContentSpec.collection.push({
  title: 'Members-only collection',
  summary: 'A collection shared under a strict protocol, visible only to its members.',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal members only'],
});

/* Define default dictionary word taxonomy terms. */
defaultContentSpec.word.push({
  term: 'Word A',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access', 'Repository under review'],
  field_dictionary_word_language: 'First language',
  field_alternate_spelling: 'woard A',
  field_definition: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed euismod eget dolor at gravida. Nulla luctus ultricies mi eget dapibus. Duis vitae luctus nunc, id tincidunt ante. Nulla enim quam, dignissim at velit ut, mollis mollis lorem. Aliquam erat volutpat. Vivamus dignissim arcu at risus gravida, et laoreet ex blandit.',
  field_sample_sentences: [
    {
      field_sentence: 'Word A Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
      upload: 'mukurtu-intro1.mp3',
    }
  ],
});
defaultContentSpec.word.push({
  term: 'Word B',
  field_cultural_protocols__sharing: 'any',
  field_cultural_protocols__value: ['Tribal community public access', 'Repository under review'],
  field_dictionary_word_language: 'Second language',
  field_alternate_spelling: 'werd B',
  field_definition: 'Aliquam erat volutpat. Aliquam erat volutpat. Phasellus vel elit at diam pulvinar tincidunt ac at mi. Mauris faucibus ultrices elit eget imperdiet. Sed sodales leo non ipsum porta blandit. Maecenas porta mauris ac lacinia tempor. Donec condimentum massa vel neque dapibus, id tristique metus consequat.',
  field_sample_sentences: [
    {
      field_sentence: 'Word B Phasellus vel elit at diam pulvinar tincidunt ac at mi.',
      upload: 'mukurtu-intro2.mp3',
    }
  ],
});

/**
 * Community name to entity id, filled in as each community is created.
 *
 * @see the accessibility membership test at the end of this file.
 */
const createdCommunityIds: Record<string, string> = {};

/**
 * Global to store if any test content exists yet.
 *
 * This value is set on the first test, and then checked on all subsequent ones.
 */
let testContentExists = null;

/**
 * Setup tasks run before each test.
 */
test.beforeEach(async ({ page }, testInfo) => {
  const login = new Login(page);
  await login.login('admin', 'admin');

  // Check if default content already exists, and if so, skip recreation.
  if (testContentExists === null) {
    await page.goto('/communities');
    const getStartedVisible = !await page.locator('.communities__item').first().isVisible();
    testContentExists = (getStartedVisible === false);
  }
  // The membership test is exempt. Whether content was seeded on this run
  // is a separate question from whether the accessibility accounts are
  // enrolled: a preview seeded before those accounts existed has content
  // but no memberships, and skipping here would leave it that way forever.
  // The test is idempotent, so re-running it on an already-enrolled site is
  // harmless.
  const seedsContent = !testInfo.title.includes('Accessibility scan account memberships');
  test.skip(testContentExists === true && seedsContent, 'Content already exists within the database, skipping the default content creation. To create default content, empty all existing content by running delete-content.spec.ts.');
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
      .getByRole('group', { name: 'Community page visibility' })
      .getByRole('radio', { name: community.field_access_mode })
      .check();
    await submitEntityForm(page, 'Save');

    // After creating the community, ensure we are redirected to create a
    // protocol associated with this community.
    await expect(page.locator('.page-title')).toContainText(`Creating cultural protocol for ${community.name}`);
    const communityIdMatch = page.url().match(/\/protocols\/protocol\/add\/community\/(\d+)/);
    if (!communityIdMatch) {
      throw new Error('Could not extract created community ID from URL.');
    }
    // Kept so the membership step below can address this community by id.
    // The default-content project is fullyParallel: false, so these tests
    // share a worker and this map survives between them.
    createdCommunityIds[community.name] = communityIdMatch[1];

    // Loop through all protocols to be created directly against this community.
    for (const protocol of community.protocols) {
      await page.getByRole('textbox', { name: 'Protocol Name' }).fill(protocol.name);
      await page
        .getByRole('group', { name: 'Cultural Protocol Type' })
        .getByRole('radio', { name: protocol.field_access_mode })
        .check();
      await submitEntityForm(page, 'Save and create another protocol');
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
    // Create through the custom admin URL.
    await page.goto('/admin/categories/manage');

    // Expand the Details element to populate a new category value.
    await page.getByRole('button', { name: 'Add a new category' }).click();

    await page.getByRole('textbox', { name: 'Category name' }).fill(category.name);

    // Fill summary and body using CKEditor 5
    const summary = new Ckeditor5(page, '[data-drupal-selector="edit-description-wrapper"]');
    await summary.fill(category.description__value);
    await page.getByLabel('Text format').selectOption(category.description__format);

    await submitEntityForm(page);
  }
});

/**
 * Initialize default person content.
 */
test('Default Content: Person', async ({ page, browserName }) => {
  // Loop through all Person items and create each one.
  for (const person of defaultContentSpec.person) {
    // Create through the custom admin URL.
    await page.goto('/admin/node/add/person');
    await page.getByRole('textbox', { name: /^Name/ }).fill(person.name);
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

    await submitEntityForm(page);
  }
});

/**
 * Initialize default DH content.
 */
test('Default Content: Digital Heritage', async ({ page, browserName }) => {
  // Loop through all Digital Heritage items and create each one.
  for (const dh of defaultContentSpec.dh) {
    // Create through the custom admin URL.
    await page.goto('/admin/node/add/digital_heritage');
    await page.getByRole('textbox', { name: 'Title' }).fill(dh.title);
    await page.getByRole('textbox', { name: 'Summary' }).fill(dh.summary);
    await page
      .getByRole('group', { name: 'Sharing Setting' })
      .getByRole('radio', { name: dh.field_cultural_protocols__sharing })
      .check();

    // Loop through the cultural protocols to be added to this DH item.
    for (const protocol of dh.field_cultural_protocols__value) {
      await page
        .getByRole('group', { name: 'Cultural Protocols' })
        .getByRole('checkbox', { name: protocol})
        .check();
    }

    // Category is required on this form. Without it the save fails
    // validation, the page stays on the form, and - because nothing
    // below checked - this loop used to pass anyway with zero items
    // created. That is how /digital-heritage read "No results" on every
    // preview while this test showed green.
    for (const category of dh.field_category) {
      await page
        .getByRole('group', { name: 'Category' })
        .getByRole('checkbox', { name: category })
        .check();
    }

    await submitEntityForm(page);
    await expect(page.getByRole('contentinfo', { name: 'Status message' }))
      .toContainText(`${dh.title} has been created.`);
  }
});

/**
 * Initialize default collection content.
 */
test('Default Content: Collection', async ({ page }) => {
  // Loop through all collections and create each one.
  for (const collection of defaultContentSpec.collection) {
    await page.goto('/node/add/collection');
    await page.getByRole('textbox', { name: 'Collection name' }).fill(collection.title);
    await page.getByRole('textbox', { name: 'Summary' }).fill(collection.summary);
    await page
      .getByRole('group', { name: 'Sharing Setting' })
      .getByRole('radio', { name: collection.field_cultural_protocols__sharing })
      .check();

    for (const protocol of collection.field_cultural_protocols__value) {
      await page
        .getByRole('group', { name: 'Cultural Protocols' })
        .getByRole('checkbox', { name: protocol })
        .check();
    }

    await submitEntityForm(page);
    await expect(page.getByRole('contentinfo', { name: 'Status message' }))
      .toContainText(`${collection.title} has been created.`);
  }
});

/**
 * Initialize default language terms.
 */
test('Default Content: Language', async ({ page, browserName }) => {
  // Loop through all Language terms and create each one.
  for (const language of defaultContentSpec.language) {
    await page.goto('/admin/structure/taxonomy/manage/language/add');
    await page.getByRole('textbox', { name: 'Name' }).fill(language.name);
    await submitEntityForm(page);
  }
});

/**
 * Initialize default dictionary word content.
 */
test('Default Content: Dictionary Word', async ({ page, browserName }) => {
  // Loop through all Dictionary word items and create each one.
  for (const word of defaultContentSpec.word) {
    // Create through the custom admin URL.
    await page.goto('/admin/node/add/dictionary_word');
    await page.getByRole('textbox', { name: 'Term' }).fill(word.term);
    await page
      .getByRole('group', { name: 'Sharing Setting' })
      .getByRole('radio', { name: word.field_cultural_protocols__sharing })
      .check();

    // Loop through the cultural protocols to be added to this word.
    for (const protocol of word.field_cultural_protocols__value) {
      await page
        .getByRole('group', { name: 'Cultural Protocols' })
        .getByRole('checkbox', { name: protocol})
        .check();
    }

    await page
      .getByRole('group', { name: 'Language' })
      .getByRole('radio', { name: word.field_dictionary_word_language, exact: true })
      .first()
      .check();
    await page.getByRole('textbox', { name: 'Alternate Spelling' }).fill(word.field_alternate_spelling);
    await new Ckeditor5(page, '[data-drupal-selector="edit-field-definition-wrapper"]').fill(word.field_definition);

    // Loop through the sample sentences to be added to this word.
    const paragraphsWrapper = page.locator('[data-drupal-selector="edit-field-sample-sentences-wrapper"]');
    for (const [index, sentence] of word.field_sample_sentences.entries()) {
      const paragraphItem = paragraphsWrapper.locator('tbody tr:nth-child(' + (index + 1) + ')');
      await paragraphItem.getByRole('textbox', { name: 'Sample Sentence' }).fill(sentence.field_sentence);
      await paragraphItem.getByRole('button', { name: 'Add media' }).click();
      await waitForAjax(page);

      // Now inside the media modal, create an audio item.
      const modal = page.getByRole('dialog', { name: 'Add or select media' });
      if (sentence.upload) {
        const uploadFilePath = path.join(__dirname, '../resources', sentence.upload);
        // Target the file input directly rather than by role. An
        // <input type="file"> has no textbox role - Claro renders it behind a
        // styled "Add file" button, and the accessibility tree exposes it as a
        // button - so getByRole('textbox') never matched and this step has
        // never actually run in CI. See setInputFiles' own documentation, which
        // recommends locating file inputs this way.
        await modal.locator('input[type="file"]').setInputFiles(uploadFilePath);
        await waitForAjax(page);

        // Sharing settings copy from the parent word access control.
        await modal
          .getByRole('group', { name: 'Sharing Setting' })
          .getByRole('radio', { name: word.field_cultural_protocols__sharing })
          .check();
        for (const protocol of word.field_cultural_protocols__value) {
          await modal
            .getByRole('group', { name: 'Cultural Protocols' })
            .getByRole('checkbox', { name: protocol })
            .check();
        }

        // Save the media item.
        await modal.getByRole('button', { name: 'Save' }).click();
        await waitForAjax(page);
      }
      else {
        // @todo: Select an existing item if not uploading a new one.
      }

      // Insert the created media item.
      await page.getByRole('button', { name: 'Insert selected' }).click();
      await waitForAjax(page);

      // Add another paragraph in case we want add another item.
      await paragraphsWrapper.getByRole('button', { name: 'Add Sample Sentence' }).click();
      await waitForAjax(page);
    }

    await submitEntityForm(page);
    await expect(page.getByRole('contentinfo', { name: 'Status message' })).toContainText(`Dictionary Word ${word.term} has been created.`)
  }


});

/**
 * Enrol the accessibility scan accounts in the seeded groups.
 *
 * The accounts themselves are created by the Tugboat build
 * (scripts/tugboat/provision-a11y-accounts.php), which runs straight after
 * drush site-install. At that point no content exists, so the script has no
 * seeded community to put them in: it used to create its own "Accessibility
 * Testing Community", and the scans then discovered *that* rather than the
 * real content. Its Local Contexts pages 404, which is how
 * manage-community-local-contexts-projects came to be skipped with
 * "returned HTTP 404" instead of scanning anything. See issue #2250.
 *
 * Seeding is the first point at which the real groups exist, so the
 * memberships belong here rather than in the build. Skips entirely when the
 * A11Y_* variables are unset, which is the same condition the provisioning
 * script uses, so a preview without them behaves exactly as before.
 */
test('Default Content: Accessibility scan account memberships', async ({ page }) => {
  const member = process.env.A11Y_USERNAME?.trim();
  const manager = process.env.A11Y_MANAGER_USERNAME?.trim();
  test.skip(!member && !manager, 'No A11Y_* accounts configured, so there is nobody to enrol.');

  // Tribal community is the one with both an open and a strict protocol, so
  // enrolling here is what lets the member scans reach protocol-gated items
  // that the anonymous scans cannot see. Without that the two surfaces scan
  // the same public content and the member pass proves nothing.
  const communityName = 'Tribal community';

  // Prefer the id recorded when the community was created, but fall back to
  // reading it off /admin/communities. The fallback matters: the seeding
  // tests skip wholesale when content already exists, so on a re-run the
  // map is empty while the community is very much there, and depending on
  // the map alone would make this throw instead of enrolling.
  let communityId = createdCommunityIds[communityName];
  if (!communityId) {
    await page.goto('/admin/communities');
    const row = page.locator('tr', { hasText: communityName });
    const href = await row.locator('a[href*="/members/add"]').first().getAttribute('href');
    communityId = href?.match(/\/admin\/communities\/(\d+)\//)?.[1] ?? '';
  }
  if (!communityId) {
    throw new Error(`Could not find the id for ${communityName}.`);
  }

  const enrol = async (username: string, communityRole: string, protocolRole: string) => {
    await page.goto(`/admin/communities/${communityId}/members/add`);

    // entity_autocomplete resolves on an exact name match, so the plain
    // username is enough and no dropdown selection is needed.
    await page.getByRole('textbox', { name: 'User' }).fill(username);
    await page
      .getByRole('checkbox', { name: `${communityRole} for ${communityName}` })
      .check();
    // Gin renders this button twice (the real one and its sticky clone),
    // and submitEntityForm()'s wrapper does not apply to this form, so
    // take the first match explicitly rather than tripping strict mode.
    await page.getByRole('button', { name: 'Next: Assign protocol roles', exact: true }).first().click({ timeout: 30000 });

    // Step two is the protocol assignment table, shown because this
    // community has child protocols.
    await page
      .getByRole('checkbox', { name: new RegExp(`${protocolRole} for `, 'i') })
      .first()
      .check();
    await page.getByRole('button', { name: 'Save', exact: true }).first().click({ timeout: 30000 });
  };

  if (member) {
    await enrol(member, 'Community Member', 'Protocol Member');
  }
  if (manager) {
    await enrol(manager, 'Community Manager', 'Protocol Steward');
  }
});

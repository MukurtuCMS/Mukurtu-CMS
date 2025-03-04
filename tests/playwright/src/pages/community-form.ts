import {Page, Locator} from '@playwright/test';
import {microContentEditor} from "~components/microcontent-editor";

const selectors = {
  communityName: '[data-drupal-selector="edit-name-0-value"]',
  description: '[data-drupal-selector="edit-field-description-0"]',
  accessMode: '[data-drupal-selector="edit-field-access-mode"]',
  communityManagersButton: '[drupal-data-selector="edit-community-manager-entity-browser-open-modal"]',
  communityMembersButton: '[drupal-data-selector="edit-community-member-entity-browser-open-modal"]',
  communityAffiliatesButton: '[drupal-data-selector="edit-community-affiliate-entity-browser-open-modal"]',
  saveButton: '[drupal-data-selector="edit-submit"]',
}

export type communityContent = {
  communityName?: string;
  description?: string;
  accessMode?: "strict"|"open";
  communityManagers?: string[];
  communityMembers?: string[];
  communityAffiliates?: string[];
};

export class CommunityForm {
  public readonly page: Page;
  public readonly communityName: Locator;
  public readonly description: microContentEditor;
  public readonly accessMode: Locator;
  public readonly communityManagersButton: Locator;
  public readonly communityMembersButton: Locator;
  public readonly communityAffiliatesButton: Locator;
  public readonly saveButton: Locator;

  public constructor(page: Page) {
    this.page = page;
    this.communityName = page.locator(selectors.communityName);
    this.description = new microContentEditor(page, selectors.description);
    this.accessMode = page.locator(selectors.accessMode);
    this.communityManagersButton = page.locator(selectors.communityManagersButton);
    this.communityMembersButton = page.locator(selectors.communityMembersButton);
    this.communityAffiliatesButton = page.locator(selectors.communityAffiliatesButton);
    this.saveButton = page.locator(selectors.saveButton);
  }

  public async newContent(page: Page, content: communityContent) {
    await this.page.goto('/communities/community/add');
    await this.communityName.click();
    await this.communityName.fill(content.communityName);
    await this.accessMode.locator(`[value="${content.accessMode}"]`).check();

    if (content.description) {
      await this.description.fillBody(content.description);
    }

    await this.saveButton.click();
  }

}

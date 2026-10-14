import {Page, test} from '@playwright/test';
import { gotoReady } from '~helpers/preview';

export class LogMessage {
  private readonly page: Page;

  public constructor(page: Page) {
    this.page = page;
  }

  /**
   * Clear all recent logs.
   */
  public async clearLogs() {
    await gotoReady(this.page, '/admin/reports/dblog/confirm');
    await this.page.locator('#edit-submit').click();
  }

  public async validateLogs() {
    await gotoReady(this.page, '/admin/reports/dblog');
    await this.page.getByLabel('Severity').selectOption({ label: 'Error' });
    await this.page.locator('#edit-submit-watchdog').click();
  }
}

import {Page, Locator, FrameLocator, expect} from '@playwright/test';
import {Ckeditor5} from "./ckeditor5";

export class microContentEditor {
  public readonly page: Page|FrameLocator;
  public readonly ckeditor5: Ckeditor5;

  public constructor(page: Page|FrameLocator, locator: string) {
    this.page = page;
    this.ckeditor5 = new Ckeditor5(page, locator);
  }

  public async fillBody(text: string): Promise<void> {
    await this.ckeditor5.fill(text);
  }
}

import {FrameLocator, Page, Locator} from "@playwright/test";

export class Ckeditor5 {
  public page: Page|FrameLocator;
  protected selector: string;

  /**
   * Constructor.
   *
   * @param page
   *   The page the ckeditor is found on.
   * @param selector
   *   A selector passed to a locator function that will return an element containing the ckeditor iframe.
   */
  public constructor(page: Page | FrameLocator, selector: string) {
    this.page = page;
    this.selector = selector;
  }

  /**
   * Fill the ckeditor field.
   *
   * @param text
   *   Text fill into ckeditor.
   */
  public async fill(text: string) {
    const contentCK =  this.page.locator(this.selector)
      .locator('.ck-editor__editable');

    await contentCK.click();
    await contentCK.fill(text);
  }
}

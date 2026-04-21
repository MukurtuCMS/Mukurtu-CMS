<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests integration with the Layout Builder module.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class LayoutBuilderTest extends SearchApiBrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'block',
    'field_ui',
    'language',
    'layout_builder',
    'search_api_test_views',
  ];

  /**
   * Tests that a Views block placed via Layout Builder is detected correctly.
   *
   * @covers \Drupal\search_api\Plugin\search_api\display\ViewsBlock::isRenderedInCurrentRequest
   */
  public function testViewsBlockRenderedInCurrentRequest(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
    ]));
    $this->drupalCreateNode();

    $this->drupalGet('admin/structure/types/manage/page/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $this->submitForm(['layout[allow_custom]' => TRUE], 'Save');

    // Go to the test node's page and verify that isRenderedInCurrentRequest()
    // returns FALSE.
    \Drupal::state()
      ->set('search_api_test_views.testViewsBlockRenderedInCurrentRequest', TRUE);
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('views_block:search_api_test_view__block_1.isRenderedInCurrentRequest(): FALSE');

    // Add our Views search block.
    $this->drupalGet('node/1/layout');
    $page->clickLink('Add block');
    $page->clickLink('Test view test block 1');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');

    // Now, isRenderedInCurrentRequest() should return TRUE.
    $assert_session->pageTextContains('views_block:search_api_test_view__block_1.isRenderedInCurrentRequest(): TRUE');
  }

}

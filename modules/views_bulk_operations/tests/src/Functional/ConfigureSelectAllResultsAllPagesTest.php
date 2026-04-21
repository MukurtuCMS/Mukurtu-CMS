<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Functional;

use Drupal\Core\Url;

/**
 * Test that we can configure "Select / Deselect all results (all pages)".
 *
 * @group views_bulk_operations
 */
final class ConfigureSelectAllResultsAllPagesTest extends ConfigureSelectionInfoTestBase {

  /**
   * Data provider for testShowSelectAll().
   *
   * @return array[]
   *   An array of argument-arrays to pass to a test function. Each array should
   *   contain:
   *   1. the value to set 'force_selection_info' to;
   *   2. the number of 'select_all' checkboxes that we should expect to see on
   *      the view when there are 0 results;
   *   3. the number of 'select_all' checkboxes that we should expect to see on
   *      the view when there is 1 page of results; and;
   *   4. the number of 'select_all' checkboxes that we should expect to see on
   *      thew view when there are 2 pages of results.
   */
  public static function dataProviderShowSelectAll(): array {
    return [
      'Default' => ['default', 0, 0, 1],
      'Always show' => ['always_show', 0, 1, 1],
      'Always hide' => ['always_hide', 0, 0, 0],
    ];
  }

  /**
   * Test 'show_select_all' when there are 0; 1 page, 2 pages of results.
   *
   * @dataProvider dataProviderShowSelectAll
   */
  public function testShowSelectAll(string $settingValue, int $countWhenZeroResults, int $countWhen1PageOfResults, int $countWhen2PageOfResults): void {
    // Setup: Always show the Multi - page Selection Box in this test, to
    // demonstrate its independence from the Select All Results checkbox.
    $this->setMultipageSelectionBoxVisibility($this->testViewConfiguration, 'always_show');

    // Setup: Modify the view configuration.
    $this->setSelectAllVisibilityConfiguration($this->testViewConfiguration, $settingValue);

    // Sanity-check the number of nodes. Navigate to the
    // views_bulk_operations_test view's page_1 display. Check the number of
    // 'select_all' checkboxes that we see on the page. The Multipage Selection
    // Box should never be visible when there are 0 results.
    self::assertEquals(0, $this->countNumberOfNodesInSystem(), 'Should be 0 nodes (0 pages) when test starts');
    $this->drupalGet(Url::fromRoute('view.views_bulk_operations_test.page_1'));
    self::assertCount($countWhenZeroResults, $this->findSelectAllCheckboxesOnPage(), 'Checking number of "select_all" checkboxes when there are 0 nodes');
    self::assertCount(0, $this->findMultipageSelectionBoxOnPage(), 'We al');

    // Setup: Create one page of results.
    $this->createPageNodes($this->numberOfResultsPerPage);

    // Sanity-check the number of nodes. Navigate to the
    // views_bulk_operations_test view's page_1 display again. Check the number
    // of 'select_all' checkboxes that we see on the page. The Multipage
    // Selection Box should always be visible during this test.
    self::assertEquals($this->numberOfResultsPerPage * 1, $this->countNumberOfNodesInSystem(), 'Should be 1 page of nodes');
    $this->drupalGet(Url::fromRoute('view.views_bulk_operations_test.page_1'));
    self::assertCount($countWhen1PageOfResults, $this->findSelectAllCheckboxesOnPage(), 'Checking number of "select_all" checkboxes when there is 1 page of nodes');
    self::assertCount(1, $this->findMultipageSelectionBoxOnPage());

    // Setup: Create a second page of results.
    $this->createPageNodes($this->numberOfResultsPerPage);

    // Sanity-check the number of nodes. Navigate to the
    // views_bulk_operations_test view's page_1 display again. Check the number
    // of 'select_all' checkboxes that we see on the page. The Multipage
    // Selection Box should always be visible during this test.
    self::assertEquals($this->numberOfResultsPerPage * 2, $this->countNumberOfNodesInSystem(), 'Should be 2 pages of nodes');
    $this->drupalGet(Url::fromRoute('view.views_bulk_operations_test.page_1'));
    self::assertCount($countWhen2PageOfResults, $this->findSelectAllCheckboxesOnPage(), 'Checking number of "select_all" checkboxes when there are 2 pages of nodes');
    self::assertCount(1, $this->findMultipageSelectionBoxOnPage());
  }

}

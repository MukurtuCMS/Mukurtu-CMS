<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies that the "Search excerpt" field in entity displays works correctly.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ExcerptFieldTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'block',
    'language',
    'search_api_test_excerpt_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $additionalBundles = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }
  }

  /**
   * Tests that the "Search excerpt" field in entity displays works correctly.
   *
   * @see search_api_test_excerpt_field_search_api_results_alter()
   */
  public function testSearchExcerptField() {
    $assertSession = $this->assertSession();

    $path = '/search-api-test-excerpt-field';
    $this->drupalGet($path);
    foreach ($this->ids as $itemId) {
      $assertSession->pageTextContains("Item $itemId test excerpt");
    }

    // Visiting the same page a second time retrieves the rendered node from
    // cache, not using the updated test excerpt template.
    \Drupal::keyValue('search_api_test')->set('excerpt_template', 'test--{{item_id}}--excerpt');
    $this->drupalGet($path);
    foreach ($this->ids as $itemId) {
      $assertSession->pageTextContains("Item $itemId test excerpt");
      $assertSession->pageTextNotContains("test--$itemId--excerpt");
    }

    // Changing the GET parameters does skip the render cache for the nodes.
    $this->drupalGet($path, ['query' => ['foo' => 'bar']]);
    foreach ($this->ids as $itemId) {
      $assertSession->pageTextContains("test--$itemId--excerpt");
      $assertSession->pageTextNotContains("Item $itemId test excerpt");
    }
  }

}

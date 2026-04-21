<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\block\Entity\Block;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Task;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cacheability metadata of Search API.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class CacheabilityTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rest',
    'search_api',
    'search_api_test',
    'search_api_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Set up example structure and content and populate the test index with
    // that content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->indexItems($this->indexId);
  }

  /**
   * Tests the cacheability settings of Search API.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    // Verify that the search results are marked as uncacheable.
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);
    // @todo Change to responseHeaderEquals() once we depend on Drupal 11.1.
    //   Expected value: "UNCACHEABLE (poor cacheability)".
    $this->assertSession()->responseHeaderContains('x-drupal-dynamic-cache', 'UNCACHEABLE');
    $this->assertSession()->responseHeaderContains('cache-control', 'no-cache');

    // Verify that the search results are displayed.
    $this->assertSession()->pageTextContains('foo test');
    $this->assertSession()->pageTextContains('foo baz');
  }

  /**
   * Tests the cache metadata of the "Execute pending tasks" action.
   */
  public function testExecuteTasksAction() {
    // Enable the "Local actions" block so we can verify which local actions are
    // displayed.
    $success = \Drupal::getContainer()
      ->get('module_installer')
      ->install(['block'], TRUE);
    $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => 'block']));
    Block::create([
      'id' => 'stark_local_actions',
      'theme' => 'stark',
      'weight' => -20,
      'plugin' => 'local_actions_block',
      'region' => 'content',
    ])->save();

    $assert_session = $this->assertSession();
    $admin_path = 'admin/config/search/search-api';

    $this->drupalLogin($this->adminUser);

    // At first, the action should not be present.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');

    // Create one task.
    $task = Task::create([]);
    $task->save();

    // Now the action should be shown.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Execute pending tasks');

    // Delete the task again.
    $task->delete();

    // Now the action should be hidden again.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
  }

  /**
   * Tests that indexing or deleting items clears the cache.
   */
  public function testViewsCacheAddRemoveContent() {
    $entity = $this->addTestEntity(6, [
      'name' => 'Fresh node',
      'body' => 'test foobar Case',
      'type' => 'item',
    ]);
    // Prime page cache before indexing.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 5 search results');

    $this->indexItems($this->indexId);

    // Check that the newly indexed node is visible on the search index.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 6 search results');

    $entity->delete();

    // Check that the deleted entity is now no longer shown.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 5 search results');
  }

  /**
   * Tests that exceptions during searches are handled correctly.
   */
  public function testExceptionHandling(): void {
    $key_value = \Drupal::keyValue('search_api_test_views');
    $key_value->set('throw_exception', TRUE);
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Test exception thrown from search_api_test_views_search_api_query_alter().');

    $key_value->set('throw_exception', FALSE);
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextNotContains('Test exception thrown from search_api_test_views_search_api_query_alter().');
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertSession()->pageTextContains('foo test');
  }

}

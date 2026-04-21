<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Highlight processor's ability to create excerpts without keywords.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Highlight
 */
#[RunTestsInSeparateProcesses]
class EmptyQueryStringExcerptTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'block',
    'language',
    'search_api_test_excerpt',
    'search_api_test_views',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

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
   * Tests the "excerpt_always" functionality of the Highlight processor.
   *
   * @param bool $enabled
   *   Whether to enable the "excerpt_always" processor setting.
   *
   * @dataProvider excerptAlwaysDataProvider
   */
  public function testExcerptAlways($enabled) {
    // Set the "excerpt_always" setting.
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/processors');
    $editForm = 'admin/config/search/search-api/index/' . $this->indexId . '/processors';
    $this->drupalGet($editForm);
    $form = [
      'status[highlight]' => 1,
      'processors[highlight][settings][excerpt_always]' => (int) $enabled,
    ];
    $this->submitForm($form, 'Save');

    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId);

    // Get the output of the view created in search_api_test_excerpt module.
    $this->drupalGet('search-api-test-search-excerpt');

    // We search the text of the label field, which will only be displayed if
    // there is an excerpt. The excerpt without any query string is always "…",
    // which cannot be checked for as reliably.
    if ($enabled) {
      $this->assertSession()->pageTextContains('Excerpt_label');
    }
    else {
      $this->assertSession()->pageTextNotContains('Excerpt_label');
    }
  }

  /**
   * Provides test data for the testExcerptAlways() test method.
   *
   * @return array
   *   An associative array of argument arrays for testExcerptAlways(), keyed by
   *   data set label.
   */
  public static function excerptAlwaysDataProvider() {
    return [
      'setting disabled' => [FALSE],
      'setting enabled' => [TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initConfig(ContainerInterface $container) {
    parent::initConfig($container);

    // This will just set the Drupal state to include the necessary bundles for
    // our test entity type. Otherwise, fields from those bundles won't be found
    // and thus removed from the test index. (We can't do it in setUp(), before
    // calling the parent method, since the container isn't set up at that
    // point.)
    $bundles = [
      'entity_test_mulrev_changed' => ['label' => 'Entity Test Bundle'],
      'item' => ['label' => 'item'],
      'article' => ['label' => 'article'],
    ];
    \Drupal::state()->set('entity_test_mulrev_changed.bundles', $bundles);
  }

}

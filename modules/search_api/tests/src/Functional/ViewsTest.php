<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\block\Entity\Block;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_test_views\EventListener;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'block',
    'language',
    'search_api_test_views',
    'views_ui',
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

    // Add a second language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

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

    $this->rebuildContainer();
  }

  /**
   * Tests a view with exposed filters.
   *
   * @see views.view.search_api_test_view.yml
   */
  public function testSearchView() {
    $this->checkResults([], array_keys($this->entities), 'Unfiltered search');

    $this->checkResults(
      ['search_api_fulltext' => 'foobar'],
      [3],
      'Search for a single word'
    );
    $this->checkResults(
      ['search_api_fulltext' => 'foo test'],
      [1, 2, 4],
      'Search for multiple words'
    );
    $query = [
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'OR search for multiple words');
    $query = [
      'search_api_fulltext' => 'foobar',
      'search_api_fulltext_op' => 'not',
    ];
    $this->checkResults($query, [1, 2, 4, 5], 'Negated search');
    $query = [
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'not',
    ];
    $this->checkResults($query, [], 'Negated search for multiple words');
    $query = [
      'search_api_fulltext' => 'fo',
    ];
    $label = 'Search for short word';
    $this->checkResults($query, [], $label);
    $this->assertSession()->pageTextContains('You must include at least one keyword to match in the content. Keywords must be at least 3 characters, and punctuation is ignored.');
    $query = [
      'search_api_fulltext' => 'foo to test',
    ];
    $label = 'Fulltext search including short word';
    $this->checkResults($query, [1, 2, 4], $label);
    $this->assertSession()->pageTextNotContains('You must include at least one keyword to match in the content. Keywords must be at least 3 characters, and punctuation is ignored.');

    // Enable the "Maximum number of words" setting.
    $view = View::load('search_api_test_view');
    $displays = $view->get('display');
    $displays['default']['display_options']['filters']['search_api_fulltext']['expose']['value_max_words'] = 2;
    $view->set('display', $displays);
    $view->save();

    $this->checkResults(['search_api_fulltext' => 'foo test'], [1, 2, 4], 'Search with multiple words should now work again');
    $this->assertSession()->pageTextNotContains('Maximum number of words exceeded.');

    $query = ['search_api_fulltext' => 'foo to test'];
    $this->checkResults($query, [], 'Search with too many words');
    $this->assertSession()->pageTextContains('Maximum number of words exceeded. You may enter a maximum of 2 words.');

    // Revert the view change to the "Maximum number of words" setting.
    $displays['default']['display_options']['filters']['search_api_fulltext']['expose']['value_max_words'] = '';
    $view->set('display', $displays);
    $view->save();

    // Use the same search query to confirm the limit is no longer enforced.
    $this->checkResults($query, [1, 2, 4], 'Search with multiple words should now work again');
    $this->assertSession()->pageTextNotContains('Maximum number of words exceeded.');

    $this->checkResults(['id[value]' => 2], [2], 'Search with ID filter');
    $query = [
      'id[min]' => 2,
      'id[max]' => 4,
      'id_op' => 'between',
    ];
    $this->checkResults($query, [2, 3, 4], 'Search with ID "in between" filter');
    $query = [
      'id[min]' => 2,
      'id_op' => 'between',
    ];
    $this->checkResults($query, [2, 3, 4, 5], 'Search with ID "in between" filter (only min)');
    $query = [
      'id[max]' => 4,
      'id_op' => 'between',
    ];
    $this->checkResults($query, [1, 2, 3, 4], 'Search with ID "in between" filter (only max)');
    $query = [
      'id[min]' => 2,
      'id[max]' => 4,
      'id_op' => 'not between',
    ];
    $this->checkResults($query, [1, 5], 'Search with ID "not in between" filter');
    $query = [
      'id[min]' => 2,
      'id_op' => 'not between',
    ];
    $this->checkResults($query, [1], 'Search with ID "not in between" filter (only min)');
    $query = [
      'id[max]' => 4,
      'id_op' => 'not between',
    ];
    $this->checkResults($query, [5], 'Search with ID "not in between" filter (only max)');
    $query = [
      'id[value]' => 2,
      'id_op' => '>',
    ];
    $this->checkResults($query, [3, 4, 5], 'Search with ID "greater than" filter');
    $query = [
      'id[value]' => 2,
      'id_op' => '!=',
    ];
    $this->checkResults($query, [1, 3, 4, 5], 'Search with ID "not equal" filter');
    $query = [
      'id_op' => 'empty',
    ];
    $this->checkResults($query, [], 'Search with ID "empty" filter');
    $query = [
      'id_op' => 'not empty',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with ID "not empty" filter');

    $yesterday = strtotime('-1DAY');
    $query = [
      'created[value]' => date('Y-m-d', $yesterday),
      'created_op' => '>',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "Created after" filter');
    $query = [
      'created[value]' => date('Y-m-d', $yesterday),
      'created_op' => '<',
    ];
    $this->checkResults($query, [], 'Search with "Created before" filter');
    $query = [
      'created_op' => 'empty',
    ];
    $this->checkResults($query, [], 'Search with "empty creation date" filter');
    $query = [
      'created_op' => 'not empty',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "not empty creation date" filter');

    $this->checkResults(['keywords[value]' => 'apple'], [2, 4], 'Search with Keywords filter');
    $query = [
      'keywords[min]' => 'aardvark',
      'keywords[max]' => 'calypso',
      'keywords_op' => 'between',
    ];
    $this->checkResults($query, [2, 4, 5], 'Search with Keywords "in between" filter');

    // For the keywords filters with comparison operators, exclude entity 1
    // since that contains all the uppercase and special characters weirdness.
    $query = [
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'melon',
      'keywords_op' => '>=',
    ];
    $this->checkResults($query, [2, 4, 5], 'Search with Keywords "greater than or equal" filter');
    $query = [
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'banana',
      'keywords_op' => '<',
    ];
    $this->checkResults($query, [2, 4], 'Search with Keywords "less than" filter');
    $query = [
      'keywords[value]' => 'orange',
      'keywords_op' => '!=',
    ];
    $this->checkResults($query, [3, 4], 'Search with Keywords "not equal" filter');
    $query = [
      'keywords_op' => 'empty',
    ];
    $label = 'Search with Keywords "empty" filter';
    $this->checkResults($query, [3], $label, 'all/all/all');
    $query = [
      'keywords_op' => 'not empty',
    ];
    $this->checkResults($query, [1, 2, 4, 5], 'Search with Keywords "not empty" filter');

    $query = [
      'name[value]' => 'foo',
    ];
    $this->checkResults($query, [1, 2, 4], 'Search with Name "contains" filter');
    $query = [
      'name[value]' => 'foo',
      'name_op' => '!=',
    ];
    $this->checkResults($query, [3, 5], 'Search with Name "doesn\'t contain" filter');
    $query = [
      'name_op' => 'empty',
    ];
    $this->checkResults($query, [], 'Search with Name "empty" filter');
    $query = [
      'name_op' => 'not empty',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with Name "not empty" filter');

    // To enable us to determine whether the language filters were correctly
    // placed with setLanguages() instead of filters on "search_api_language",
    // we activate printing of the query to the results page.
    \Drupal::state()->set(EventListener::STATE_PRINT_QUERY, TRUE);
    $query = [
      'language' => ['***LANGUAGE_site_default***'],
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "Page content language" filter');
    $this->assertSession()->pageTextContains('Searched languages: en');
    $this->assertSession()->pageTextNotContains('search_api_language');
    $this->assertSession()->pageTextContains('search_api_included_languages');
    $query = [
      'language' => ['en'],
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "English" language filter');
    $this->assertSession()->pageTextContains('Searched languages: en');
    $this->assertSession()->pageTextNotContains('search_api_language');
    $this->assertSession()->pageTextContains('search_api_included_languages');
    $query = [
      'language' => ['und'],
    ];
    $this->checkResults($query, [], 'Search with "Not specified" language filter');
    $this->assertSession()->pageTextContains('Searched languages: und');
    $this->assertSession()->pageTextNotContains('search_api_language');
    $this->assertSession()->pageTextContains('search_api_included_languages');
    $query = [
      'language' => [
        '***LANGUAGE_language_interface***',
        'zxx',
      ],
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with multiple languages filter');
    $this->assertSession()->pageTextContains('Searched languages: en, zxx');
    $this->assertSession()->pageTextNotContains('search_api_language');
    $this->assertSession()->pageTextContains('search_api_included_languages');
    \Drupal::state()->delete(EventListener::STATE_PRINT_QUERY);

    $query = [
      'search_api_fulltext' => 'foo to test',
      'id[value]' => 2,
      'id_op' => '>',
      'keywords_op' => 'not empty',
    ];
    $this->checkResults($query, [4], 'Search with multiple filters');

    // Test contextual filters. Configured contextual filters are:
    // 1: datasource
    // 2: type (not = true)
    // 3: keywords (break_phrase = true)
    $this->checkResults([], [4, 5], 'Search with arguments', 'entity:entity_test_mulrev_changed/item/grape');

    // "Type" doesn't have "break_phrase" enabled, so the second argument won't
    // have any effect.
    $this->checkResults([], [2, 4, 5], 'Search with arguments', 'all/item+article/strawberry+apple');

    // Check "OR" contextual filters (using commas).
    $this->checkResults([], [4], 'Search with OR arguments', 'all/item,article/strawberry,apple');

    $this->checkResults([], [], 'Search with unknown datasource argument', 'entity:foobar/all/all');

    $query = [
      'id[value]' => 1,
      'id_op' => '!=',
      'keywords[value]' => 'melon',
      'keywords_op' => '>=',
    ];
    $this->checkResults($query, [2, 5], 'Search with arguments and filters', 'entity:entity_test_mulrev_changed/all/orange');

    // Make sure the datasource filter works correctly with multiple selections.
    $index = Index::load($this->indexId);
    $datasource = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createDatasourcePlugin($index, 'entity:user');
    $index->addDatasource($datasource);
    $index->save();

    $query = [
      'datasource' => ['entity:user', 'entity:entity_test_mulrev_changed'],
      'datasource_op' => 'or',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with multiple datasource filters (OR)');

    $query = [
      'datasource' => ['entity:user', 'entity:entity_test_mulrev_changed'],
      'datasource_op' => 'and',
    ];
    $this->checkResults($query, [], 'Search with multiple datasource filters (AND)');

    $query = [
      'datasource' => ['entity:user'],
      'datasource_op' => 'not',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search for non-user results');

    $query = [
      'datasource' => ['entity:entity_test_mulrev_changed'],
      'datasource_op' => 'not',
    ];
    $this->checkResults($query, [], 'Search for non-test entity results');

    $query = [
      'datasource' => ['entity:user', 'entity:entity_test_mulrev_changed'],
      'datasource_op' => 'not',
    ];
    $this->checkResults($query, [], 'Search for results of no available datasource');

    $this->regressionTests();

    // Check special functionality that requires editing the view.
    $this->checkExposedSearchFields();

    // Make sure there was a display plugin created for this view.
    /** @var \Drupal\search_api\Display\DisplayInterface[] $displays */
    $displays = \Drupal::getContainer()
      ->get('plugin.manager.search_api.display')
      ->getInstances();

    $display_id = 'views_page:search_api_test_view__page_1';
    $this->assertArrayHasKey($display_id, $displays, 'A display plugin was created for the test view page display.');
    $this->assertArrayHasKey('views_block:search_api_test_view__block_1', $displays, 'A display plugin was created for the test view block display.');
    $this->assertArrayHasKey('views_rest:search_api_test_view__rest_export_1', $displays, 'A display plugin was created for the test view block display.');
    $this->assertEquals('/search-api-test', $displays[$display_id]->getPath(), 'Display returns the correct path.');
    $view_url = Url::fromUserInput('/search-api-test')->toString();
    $display_url = Url::fromUserInput($displays[$display_id]->getPath())->toString();
    $this->assertEquals($view_url, $display_url, 'Display returns the correct URL.');
    $this->assertNull($displays['views_block:search_api_test_view__block_1']->getPath(), 'Block display returns the correct path.');
    $this->assertEquals('/search-api-rest-test', $displays['views_rest:search_api_test_view__rest_export_1']->getPath(), 'REST display returns the correct path.');

    $this->assertEquals('database_search_index', $displays[$display_id]->getIndex()->id(), 'Display returns the correct search index.');

    $admin_user = $this->drupalCreateUser([
      'administer search_api',
      'access administration pages',
      'administer views',
    ]);
    $this->drupalLogin($admin_user);

    // Delete the page display for the view.
    $this->drupalGet('admin/structure/views/view/search_api_test_view/edit/page_1');
    $this->submitForm([], 'Delete Page');
    $this->submitForm([], 'Save');

    drupal_flush_all_caches();

    $displays = \Drupal::getContainer()
      ->get('plugin.manager.search_api.display')
      ->getInstances();
    $this->assertArrayNotHasKey('views_page:search_api_test_view__page_1', $displays, 'No display plugin was created for the test view page display.');
    $this->assertArrayHasKey('views_block:search_api_test_view__block_1', $displays, 'A display plugin was created for the test view block display.');
    $this->assertArrayHasKey('views_rest:search_api_test_view__rest_export_1', $displays, 'A display plugin was created for the test view block display.');
  }

  /**
   * Tests a view with operations column.
   */
  public function testViewWithOperations() {
    $this->drupalGet('search-api-test-operations/', ['query' => []]);

    // Checking first and last item in result.
    $this->assertSession()->linkByHrefExists('/entity_test_mulrev_changed/manage/1/edit');
    $this->assertSession()->linkByHrefExists('/entity_test/delete/entity_test_mulrev_changed/1');
    $this->assertSession()->linkByHrefExists('/entity_test_mulrev_changed/manage/5/edit');
    $this->assertSession()->linkByHrefExists('/entity_test/delete/entity_test_mulrev_changed/5');

    // Checking item without operations.
    $this->assertSession()->linkByHrefNotExists('/entity_test_mulrev_changed/manage/2/edit');
    $this->assertSession()->linkByHrefNotExists('/entity_test/delete/entity_test_mulrev_changed/2');
  }

  /**
   * Contains regression tests for previous, fixed bugs.
   */
  protected function regressionTests() {
    $this->regressionTest3296477();
    $this->regressionTest3318187();
    $this->regressionTest3187134();
    $this->regressionTest2869121();
    $this->regressionTest3031991();
    $this->regressionTest3136277();
    $this->regressionTest3029582();
    $this->regressionTest3343250();
  }

  /**
   * Tests setting the "Fulltext search" filter to "Required".
   *
   * This previously caused problems with form validation and caching.
   *
   * @see https://www.drupal.org/node/2869121
   * @see https://www.drupal.org/node/2873246
   * @see https://www.drupal.org/node/2871030
   */
  protected function regressionTest2869121() {
    // Make sure setting the fulltext filter to "Required" works as expected.
    $view = View::load('search_api_test_view');
    $displays = $view->get('display');
    $displays['default']['display_options']['filters']['search_api_fulltext']['expose']['required'] = TRUE;
    $displays['default']['display_options']['cache']['type'] = 'search_api_time';
    $view->set('display', $displays);
    $view->save();

    $this->checkResults([], [], 'Search without required fulltext keywords');
    $this->assertSession()->responseNotContains('Error message');
    $this->checkResults(
      ['search_api_fulltext' => 'foo test'],
      [1, 2, 4],
      'Search for multiple words'
    );
    $this->assertSession()->responseNotContains('Error message');
    $this->checkResults(
      ['search_api_fulltext' => 'fo'],
      [],
      'Search for short word'
    );
    $this->assertSession()->pageTextContains('You must include at least one keyword to match in the content. Keywords must be at least 3 characters, and punctuation is ignored.');

    // Make sure this also works with the exposed form in a block, and doesn't
    // throw fatal errors on all pages with the block.
    $view = View::load('search_api_test_view');
    $displays = $view->get('display');
    $displays['page_1']['display_options']['exposed_block'] = TRUE;
    $view->set('display', $displays);
    $view->save();

    Block::create([
      'id' => 'search_api_test_view',
      'theme' => $this->defaultTheme,
      'weight' => -20,
      'plugin' => 'views_exposed_filter_block:search_api_test_view-page_1',
      'region' => 'content',
    ])->save();

    $this->drupalGet('');
    // We submit the form three times, to make extra sure all Views caches are
    // triggered.
    for ($i = 0; $i < 3; ++$i) {
      // Flush the page-level caches to make sure the Views cache plugin is
      // used (so we could reproduce the bug if it's there).
      \Drupal::getContainer()->get('cache.page')->deleteAll();
      \Drupal::getContainer()->get('cache.dynamic_page_cache')->deleteAll();
      $this->submitForm([], 'Search');
      $this->assertSession()->addressMatches('#^/search-api-test#');
      $this->assertSession()->responseNotContains('Error message');
      $this->assertSession()->pageTextNotContains('search results');
      // Make sure the Views cache was used, none of the two page caches.
      $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
      $this->assertSession()
        ->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    }
  }

  /**
   * Tests the interaction of multiple fulltext filters.
   *
   * @see https://www.drupal.org/node/3031991
   */
  protected function regressionTest3031991() {
    $query = [
      // cspell:disable-next-line
      'search_api_fulltext' => 'foo blabla',
      'search_api_fulltext_op' => 'or',
      'search_api_fulltext_2' => 'bar',
      'search_api_fulltext_2_op' => 'not',
    ];
    $this->checkResults($query, [4], 'Search with multiple fulltext filters');
  }

  /**
   * Tests that query preprocessing works correctly for block views.
   *
   * @see https://www.drupal.org/node/3136277
   */
  protected function regressionTest3136277() {
    $block = $this->drupalPlaceBlock('views_block:search_api_test_block_view-block_1', [
      'region' => 'content',
    ]);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'ignorecase');
    $index->addProcessor($processor)->save();

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Search API Test Block View: Found 4 items');

    $index->removeProcessor('ignorecase')->save();
    $block->delete();
  }

  /**
   * Tests that arguments play well with multiple filter groups combined by OR.
   *
   * @see https://www.drupal.org/node/3029582
   */
  protected function regressionTest3029582() {
    $yesterday = date('Y-m-d', strtotime('-1DAY'));

    // Should result in these filters:
    // [
    //   keywords = 'orange'
    // AND
    //   [
    //     [
    //       id = 5
    //     AND
    //       created > $yesterday
    //     ]
    //   OR
    //     [
    //       type = 'item'
    //     AND
    //       name = 'foo'
    //     ]
    //   ]
    // ]
    // Therefore, results 1, 2 and 5 should be returned.
    $this->checkResults(
      [
        'id[value]' => '5',
        'created[value]' => $yesterday,
        'created_op' => '>',
        'type[value]' => 'item',
        'name[value]' => 'foo',
      ],
      [1, 2, 5],
      'Regression Test #3029582 - Search 1',
      'orange',
      'search-api-test-3029582',
    );

    // Should result in these filters:
    // [
    //   keywords = 'orange'
    // AND
    //   [
    //     [
    //       id = 5
    //     AND
    //       created < $yesterday
    //     ]
    //   OR
    //     [
    //       type = 'item'
    //     AND
    //       name = 'foo'
    //     ]
    //   ]
    // ]
    // Therefore, results 1 and 2 should be returned.
    $this->checkResults(
      [
        'id[value]' => '5',
        'created[value]' => $yesterday,
        'created_op' => '<',
        'type[value]' => 'item',
        'name[value]' => 'foo',
      ],
      [1, 2],
      'Regression Test #3029582 - Search 2',
      'orange',
      'search-api-test-3029582',
    );

    // Should result in these filters:
    // [
    //   keywords = 'strawberry'
    // AND
    //   [
    //     [
    //       id = 5
    //     AND
    //       created < $yesterday
    //     ]
    //   OR
    //     [
    //       type = 'item'
    //     AND
    //       name = 'foo'
    //     ]
    //   ]
    // ]
    // Therefore, no results should be returned.
    $this->checkResults(
      [
        'id[value]' => '5',
        'created[value]' => $yesterday,
        'created_op' => '<',
        'type[value]' => 'item',
        'name[value]' => 'foo',
      ],
      [],
      'Regression Test #3029582 - Search 3',
      'strawberry',
      'search-api-test-3029582',
    );

    // Should result in these filters:
    // [
    //   keywords = 'strawberry'
    // AND
    //   [
    //     [
    //       id = 5
    //     AND
    //       created < $yesterday
    //     ]
    //   OR
    //     [
    //       type = 'article'
    //     AND
    //       name = 'foo'
    //     ]
    //   ]
    // ]
    // Therefore, result 4 should be returned.
    $this->checkResults(
      [
        'id[value]' => '5',
        'created[value]' => $yesterday,
        'created_op' => '<',
        'type[value]' => 'article',
        'name[value]' => 'foo',
      ],
      [4],
      'Regression Test #3029582 - Search 3',
      'strawberry',
      'search-api-test-3029582',
    );
  }

  /**
   * Tests that arguments play well with multiple filter groups combined by OR.
   *
   * @see https://www.drupal.org/node/3343250
   */
  protected function regressionTest3343250(): void {
    $yesterday = date('Y-m-d', strtotime('-1DAY'));
    $today = date('Y-m-d');
    $query = [
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
      'created[min]' => $today,
      'created[max]' => $today,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "Created between TODAY and TODAY" filter');
    $query = [
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
      'created[min]' => $yesterday,
      'created[max]' => $today,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "Created between YESTERDAY and TODAY" filter');
    $query = [
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
      'created[min]' => $yesterday,
      'created[max]' => $yesterday,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [], 'Search with "Created between YESTERDAY and YESTERDAY" filter');
  }

  /**
   * Tests that filters with empty values are ignored.
   *
   * @see https://www.drupal.org/node/3318187
   */
  protected function regressionTest3318187() {
    \Drupal::state()->set(EventListener::STATE_PRINT_QUERY, TRUE);
    $this->checkResults(
      [
        'id[value]' => '',
        'created[value]' => '',
        'type[value]' => '',
        'name[value]' => '',
      ],
      [1, 2, 3, 4, 5],
      'Regression Test #3306204',
    );
    $this->assertSession()->pageTextContains('Index: database_search_index');
    $this->assertSession()->pageTextNotContains("id = ''");
    $this->assertSession()->pageTextNotContains("created = ''");
    $this->assertSession()->pageTextNotContains("created = 0");
    $this->assertSession()->pageTextNotContains("type = ''");
    $this->assertSession()->pageTextNotContains("name = ''");
    \Drupal::state()->delete(EventListener::STATE_PRINT_QUERY);
  }

  /**
   * Tests that date range end dates can be displayed.
   *
   * @see https://www.drupal.org/node/3187134
   */
  protected function regressionTest3187134() {
    // Install the Datetime Range module.
    // @see \Drupal\Core\Test\FunctionalTestSetupTrait::installModulesFromClassProperty()
    $modules = ['datetime', 'datetime_range'];
    $success = $this->container->get('module_installer')
      ->install($modules, TRUE);
    $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => implode(', ', $modules)]));

    // Create a date range field and add its end date to the index.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date_range',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'daterange',
      'settings' => [
        'datetime_type' => DateRangeItem::DATETIME_TYPE_DATETIME,
      ],
      'cardinality' => 1,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'item',
    ])->save();
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($index, 'field_date_range_end', [
        'label' => 'Date range (end)',
        'type' => 'date',
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'field_date_range:end_value',
      ]);
    $index->addField($field)->save();

    // Make sure this all worked correctly.
    $this->assertNotEmpty($field->getDataDefinition());

    // Set values for the new field and re-index.
    $entity = EntityTestMulRevChanged::load(reset($this->entities)->id());
    $this->assertEquals('item', $entity->bundle());
    $entity->field_date_range = [
      'value' => '2021-01-11T10:12:02',
      'end_value' => '2021-01-22T10:12:02',
    ];
    $entity->save();
    $this->indexItems($this->indexId);

    // Finally, add the field to the view. (We use the "page_2" display as that
    // already uses the "Fields" row style.
    $key = 'display.page_2.display_options.fields';
    $view = \Drupal::configFactory()->getEditable('views.view.search_api_test_view');
    $fields = $view->get($key);
    $fields['field_date_range_end'] = [
      'id' => 'field_date_range_end',
      'table' => 'search_api_index_database_search_index',
      'field' => 'field_date_range_end',
      'plugin_id' => 'search_api_date',
      'date_format' => 'custom',
      'custom_date_format' => 'Y-m-d',
      'timezone' => 'UTC',
    ];
    $view->set($key, $fields);
    $view->save();

    // Now visit the page and check if it goes "boom".
    $this->drupalGet('search-api-test-operations');
    $this->assertSession()->pageTextContains('2021-01-22');
  }

  /**
   * Tests that date "in between" filters also work with just one value.
   *
   * @see https://www.drupal.org/node/3296477
   */
  protected function regressionTest3296477(): void {
    $yesterday = date('Y-m-d', strtotime('-1DAY'));
    $tomorrow = date('Y-m-d', strtotime('+1DAY'));
    $query = [
      'created[min]' => $yesterday,
      'created[max]' => $tomorrow,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with "Created between TODAY and TOMORROW" filter');
    $query = [
      'created[min]' => $tomorrow,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [], 'Search with "Created between TOMORROW and *" filter');
    $query = [
      'created[max]' => $yesterday,
      'created_op' => 'between',
    ];
    $this->checkResults($query, [], 'Search with "Created between * and YESTERDAY" filter');
  }

  /**
   * Verifies that exposed fulltext fields work correctly.
   */
  protected function checkExposedSearchFields() {
    $key = 'display.default.display_options.filters.search_api_fulltext.expose.expose_fields';
    $view = \Drupal::configFactory()
      ->getEditable('views.view.search_api_test_view');
    $view->set($key, TRUE);
    $view->save();

    $query = [
      'search_api_fulltext' => 'foo',
      'search_api_fulltext_searched_fields' => [
        'name',
      ],
    ];
    $this->checkResults($query, [1, 2, 4], 'Search for results in name field only');

    $query = [
      'search_api_fulltext' => 'foo',
      'search_api_fulltext_searched_fields' => [
        'body',
      ],
    ];
    $this->checkResults($query, [5], 'Search for results in body field only');

    $view->set($key, FALSE);
    $view->save();
  }

  /**
   * Checks the Views results for a certain set of parameters.
   *
   * @param array $query
   *   The GET parameters to set for the view.
   * @param int[]|null $expected_results
   *   (optional) The IDs of the expected results; or NULL to skip checking the
   *   results.
   * @param string $label
   *   (optional) A label for this search, to include in assert messages.
   * @param string $arguments
   *   (optional) A string to append to the search path.
   */
  protected function checkResults(array $query, ?array $expected_results = NULL, string $label = 'Search', string $arguments = '', string $path = 'search-api-test'): void {
    $this->drupalGet($path . '/' . $arguments, ['query' => $query]);

    if (isset($expected_results)) {
      $count = count($expected_results);
      if ($count) {
        $this->assertSession()->pageTextContains("Displaying $count search results");
      }
      else {
        $this->assertSession()->pageTextNotContains('search results');
      }

      $expected_results = array_combine($expected_results, $expected_results);
      $actual_results = [];
      foreach ($this->entities as $id => $entity) {
        $entity_label = Html::escape($entity->label());
        if (str_contains($this->getSession()->getPage()->getContent(), ">$entity_label<")) {
          $actual_results[$id] = $id;
        }
      }
      $this->assertEquals($expected_results, $actual_results, "$label returned correct results.");
    }
  }

  /**
   * Tests results are ordered correctly and react to exposed sorts.
   */
  public function testViewSorts() {
    // Check default ordering, first exposed sort in config is
    // search_api_relevance.
    $this->checkResultsOrder([], [1, 2, 3, 4, 5]);

    // Make sure the exposed sort works.
    $query = [
      'sort_by' => 'search_api_id_desc',
    ];
    $this->checkResultsOrder($query, [5, 4, 3, 2, 1]);
  }

  /**
   * Checks whether Views results are in a certain order in the sorts test view.
   *
   * @param array $query
   *   The GET parameters to set for the view.
   * @param int[] $expected_results
   *   The IDs of the expected results.
   *
   * @see views.view.search_api_test_sorts.yml
   */
  protected function checkResultsOrder(array $query, array $expected_results) {
    $this->drupalGet('search-api-test-sorts', ['query' => $query]);

    $web_assert = $this->assertSession();
    $rows_xpath = '//div[contains(@class, "views-row")]';
    $web_assert->elementsCount('xpath', $rows_xpath, count($expected_results));
    foreach (array_values($expected_results) as $i => $id) {
      $entity_label = Html::escape($this->entities[$id]->label());
      // XPath offsets are 1-based, not 0-based.
      ++$i;
      $web_assert->elementContains('xpath', "($rows_xpath)[$i]", $entity_label);
    }
  }

  /**
   * Tests the Views admin UI and field handlers.
   */
  public function testViewsAdmin() {
    // Add a field from a related entity to the index to test whether it gets
    // displayed correctly.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $datasource_id = 'entity:entity_test_mulrev_changed';
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($index, 'author', [
        'label' => 'Author name',
        'type' => 'string',
        'datasource_id' => $datasource_id,
        'property_path' => 'user_id:entity:name',
      ]);
    $index->addField($field);
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($index, 'rendered_item', [
        'label' => 'Rendered HTML output',
        'type' => 'text',
        'property_path' => 'rendered_item',
        'configuration' => [
          'roles' => [AccountInterface::ANONYMOUS_ROLE],
          'view_mode' => [
            $datasource_id => [
              'article' => 'full',
              'item' => 'full',
            ],
          ],
        ],
      ]);
    $index->addField($field);
    $index->save();

    // Add some Dutch nodes.
    foreach ([1, 2, 3, 4, 5] as $id) {
      $entity = EntityTestMulRevChanged::load($id);
      $entity = $entity->addTranslation('nl', [
        'body' => "dutch node $id",
        'category' => "dutch category $id",
        'keywords' => ["dutch $id A", "dutch $id B"],
      ]);
      $entity->save();
    }
    $this->entities = EntityTestMulRevChanged::loadMultiple();
    $this->indexItems($this->indexId);

    // For viewing the user name and roles of the user associated with test
    // entities, the logged-in user needs to have the permission to administer
    // both users and permissions.
    $permissions = [
      'administer search_api',
      'access administration pages',
      'administer views',
      'administer users',
      'administer permissions',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/views/view/search_api_test_view/edit/page_1');
    $this->assertSession()->statusCodeEquals(200);

    // Set the user IDs associated with our test entities.
    $users[] = $this->createUser();
    $users[] = $this->createUser();
    $users[] = $this->createUser();
    $this->entities[1]->setOwnerId($users[0]->id())->save();
    $this->entities[2]->setOwnerId($users[0]->id())->save();
    $this->entities[3]->setOwnerId($users[1]->id())->save();
    $this->entities[4]->setOwnerId($users[1]->id())->save();
    $this->entities[5]->setOwnerId($users[2]->id())->save();

    // Switch to "Table" format.
    $this->clickLink('Unformatted list');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'style[type]' => 'table',
    ];
    $this->submitForm($edit, 'Apply');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Apply');
    $this->assertSession()->statusCodeEquals(200);

    // Add the "User ID" relationship.
    $this->clickLink('Add relationships');
    $edit = [
      'name[search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id]' => 'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id',
    ];
    $this->submitForm($edit, 'Add and configure relationships');
    $this->submitForm([], 'Apply');

    // Add new fields. First check that the listing seems correct.
    $this->clickLink('Add fields');
    $this->assertSession()->statusCodeEquals(200);
    // The entity type's label was changed in 10.1.x, so need to keep it
    // variable as long as we support versions older than 10.1.0.
    $this->assertSession()->pageTextContains("Test entity - mul changed revisions and data table datasource");
    $this->assertSession()->pageTextContains('Authored on');
    $this->assertSession()->pageTextContains('Body (indexed field)');
    $this->assertSession()->pageTextContains('Index Test index');
    $this->assertSession()->pageTextContains('Item ID');
    $this->assertSession()->pageTextContains('Excerpt');
    $this->assertSession()->pageTextContains('The search result excerpted to show found search terms');
    $this->assertSession()->pageTextContains('Relevance');
    $this->assertSession()->pageTextContains('The relevance of this search result with respect to the query');
    $this->assertSession()->pageTextContains('Language code');
    $this->assertSession()->pageTextContains('The user language code.');
    $this->assertSession()->pageTextContains('(No description available)');
    $this->assertSession()->pageTextContains('Item URL');
    $this->assertSession()->pageTextNotContains('Error: missing help');

    // Then add some fields.
    $fields = [
      'views.counter',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.id',
      'search_api_index_database_search_index.search_api_datasource',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.body',
      'search_api_index_database_search_index.category',
      'search_api_index_database_search_index.keywords',
      'search_api_datasource_database_search_index_entity_entity_test_mulrev_changed.user_id',
      'search_api_entity_user.name',
      'search_api_index_database_search_index.author',
      'search_api_entity_user.roles',
      'search_api_index_database_search_index.rendered_item',
      'search_api_index_database_search_index.search_api_rendered_item',
      'search_api_index_database_search_index.search_api_url',
    ];
    $edit = [];
    foreach ($fields as $field) {
      $edit["name[$field]"] = $field;
    }
    $this->submitForm($edit, 'Add and configure fields');
    $this->assertSession()->statusCodeEquals(200);

    // @todo For some strange reason, the "roles" field form is not included
    //   automatically in the series of field forms shown to us by Views. Deal
    //   with this graciously (since it's not really our fault, I hope), but it
    //   would be great to have this working normally.
    $get_field_id = function ($key) {
      return Utility::splitPropertyPath($key, TRUE, '.')[1];
    };
    $fields = array_map($get_field_id, $fields);
    $fields = array_combine($fields, $fields);
    for ($i = 0; $i < count($fields); ++$i) {
      $field = $this->submitFieldsForm();
      if (!$field) {
        break;
      }
      unset($fields[$field]);
    }
    foreach ($fields as $field) {
      $this->drupalGet('admin/structure/views/nojs/handler/search_api_test_view/page_1/field/' . $field);
      $this->submitFieldsForm();
    }

    // Add click sorting for all fields where this is possible.
    $this->clickLink('Settings', 0);
    $edit = [
      'style_options[info][search_api_datasource][sortable]' => 1,
      'style_options[info][category][sortable]' => 1,
      'style_options[info][keywords][sortable]' => 1,
    ];
    $this->submitForm($edit, 'Apply');

    // Add a filter for the "Name" field.
    $this->clickLink('Add filter criteria');
    $edit = [
      'name[search_api_index_database_search_index.name]' => 'search_api_index_database_search_index.name',
    ];
    $this->submitForm($edit, 'Add and configure filter criteria');
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
    ];
    $this->submitForm($edit, 'Expose filter');
    $this->submitPluginForm([]);

    // Add a "Search: Fulltext search" filter.
    $this->clickLink('Add filter criteria');
    $edit = [
      'name[search_api_index_database_search_index.search_api_fulltext]' => 'search_api_index_database_search_index.search_api_fulltext',
    ];
    $this->submitForm($edit, 'Add and configure filter criteria');
    $this->assertSession()->pageTextNotContains('No UI parse mode');
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
    ];
    $this->submitForm($edit, 'Expose filter');
    $this->submitPluginForm([]);

    // Save the view.
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Check the results.
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);

    $fields = [
      'search_api_datasource',
      'id',
      'body',
      'category',
      'keywords',
      'user_id',
      'user_id:name',
      'user_id:roles',
      'rendered_item',
      'search_api_rendered_item',
      'search_api_url',
    ];
    $rendered_item_fields = ['rendered_item', 'search_api_rendered_item'];
    foreach ($this->entities as $id => $entity) {
      foreach ($fields as $field) {
        $field_entity = $entity;
        while (strpos($field, ':')) {
          [$direct_property, $field] = Utility::splitPropertyPath($field, FALSE);
          if (empty($field_entity->{$direct_property}[0]->entity)) {
            continue 2;
          }
          $field_entity = $field_entity->{$direct_property}[0]->entity;
        }
        // Check that both the English and the Dutch entity are present in the
        // results, with their correct field values.
        $entities = [$field_entity];
        if ($field_entity->hasTranslation('nl')) {
          $entities[] = $field_entity->getTranslation('nl');
        }
        foreach ($entities as $i => $field_entity) {
          if ($field === 'search_api_datasource') {
            $data = [$datasource_id];
          }
          elseif ($field === 'search_api_url') {
            $data = [$field_entity->toUrl()->toString()];
          }
          elseif (in_array($field, $rendered_item_fields)) {
            $view_mode = $field === 'rendered_item' ? 'full' : 'teaser';
            $data = [$view_mode];
          }
          else {
            $data = \Drupal::getContainer()
              ->get('search_api.fields_helper')
              ->extractFieldValues($field_entity->get($field));
            if (!$data) {
              $data = ['[EMPTY]'];
            }
          }
          $row_num = 2 * $id + $i - 1;
          $prefix = "#$row_num [$field] ";
          $text = $prefix . implode("|$prefix", $data);
          $this->assertSession()->pageTextContains($text);
          // Special case for field "author", which duplicates content of
          // "name".
          if ($field === 'name') {
            $text = str_replace('[name]', '[author]', $text);
            $this->assertSession()->pageTextContains($text);
          }
        }
      }
    }

    // Check whether the expected retrieved fields were listed on the page.
    // These are only "keywords" and "rendered_item", since only fields that
    // correspond to an indexed field are included (not when a field is added
    // via the datasource table), and only if "Use entity field rendering" is
    // disabled.
    // @see search_api_test_views_search_api_query_alter()
    $retrieved_fields = [
      'keywords',
      'rendered_item',
    ];
    foreach ($retrieved_fields as $field_id) {
      $this->assertSession()->pageTextContains("'$field_id'");
    }

    // Check that click-sorting works correctly.
    $options = [
      'query' => [
        'order' => 'category',
        'sort' => 'asc',
      ],
    ];
    $this->drupalGet('search-api-test', $options);
    $this->assertSession()->statusCodeEquals(200);
    $ordered_categories = [
      '[EMPTY]',
      'article_category',
      'article_category',
      'dutch category 1',
      'dutch category 2',
      'dutch category 3',
      'dutch category 4',
      'dutch category 5',
      'item_category',
      'item_category',
    ];
    foreach ($ordered_categories as $i => $category) {
      ++$i;
      $this->assertSession()->pageTextContains("#$i [category] $category");
    }
    $options['query']['sort'] = 'desc';
    $this->drupalGet('search-api-test', $options);
    $this->assertSession()->statusCodeEquals(200);
    foreach (array_reverse($ordered_categories) as $i => $category) {
      ++$i;
      $this->assertSession()->pageTextContains("#$i [category] $category");
    }

    // Check the results with an anonymous visitor. All "name" fields should be
    // empty.
    $this->drupalLogout();
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);
    $html = $this->getSession()->getPage()->getContent();
    $this->assertEquals(10, substr_count($html, '[name] [EMPTY]'));

    // Set "Skip access checks" on the "user_id" relationship and check again.
    // The "name" field should now be listed regardless.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/views/nojs/handler/search_api_test_view/page_1/relationship/user_id');
    $this->submitForm(['options[skip_access]' => 1], 'Apply');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Set query tags.
    $this->drupalGet('admin/structure/views/nojs/display/search_api_test_view/page_1/query');
    $this->submitForm(['query[options][query_tags]' => 'weather'], 'Apply');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Sunshine');
    $this->drupalGet('admin/structure/views/nojs/display/search_api_test_view/page_1/query');
    $this->submitForm(['query[options][query_tags]' => ''], 'Apply');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('[name] [EMPTY]');

    // Run regression tests.
    $this->drupalLogin($admin_user);
    $this->adminUiRegressionTests();
  }

  /**
   * Submits the field handler config form currently displayed.
   *
   * @return string|null
   *   The field ID of the field whose form was submitted. Or NULL if the
   *   current page is no field form.
   */
  protected function submitFieldsForm() {
    $url_parts = explode('/', $this->getUrl());
    $field = array_pop($url_parts);
    if (array_pop($url_parts) != 'field') {
      return NULL;
    }

    $non_entity_fields = [
      'search_api_datasource',
      'rendered_item',
      'search_api_rendered_item',
      'search_api_url',
    ];
    // The "Fallback options" are only available for fields based on the Field
    // API.
    if (!in_array($field, $non_entity_fields, TRUE)) {
      $edit['options[fallback_options][multi_separator]'] = '|';
    }
    $edit['options[alter][alter_text]'] = TRUE;
    $edit['options[alter][text]'] = "#{{counter}} [$field] {{ $field }}";
    $edit['options[empty]'] = "#{{counter}} [$field] [EMPTY]";

    switch ($field) {
      case 'counter':
        $edit = [
          'options[exclude]' => TRUE,
        ];
        break;

      case 'id':
        $edit['options[field_rendering]'] = FALSE;
        break;

      case 'search_api_datasource':
        break;

      case 'body':
        break;

      case 'category':
        break;

      case 'keywords':
        $edit['options[field_rendering]'] = FALSE;
        break;

      case 'user_id':
        $edit['options[field_rendering]'] = FALSE;
        $edit['options[fallback_options][display_methods][user][display_method]'] = 'id';
        break;

      case 'author':
        break;

      case 'roles':
        $edit['options[field_rendering]'] = FALSE;
        $edit['options[fallback_options][display_methods][user_role][display_method]'] = 'id';
        break;

      case 'rendered_item':
        break;

      case 'search_api_rendered_item':
        $edit['options[view_modes][entity:entity_test_mulrev_changed][article]'] = 'teaser';
        $edit['options[view_modes][entity:entity_test_mulrev_changed][item]'] = 'teaser';
        break;
    }

    $this->submitPluginForm($edit);

    return $field;
  }

  /**
   * Submits a Views plugin's configuration form.
   *
   * @param array $edit
   *   The values to set in the form.
   */
  protected function submitPluginForm(array $edit) {
    $button_label = 'Apply';
    $buttons = $this->xpath('//input[starts-with(@value, :label)]', [':label' => $button_label]);
    if ($buttons) {
      $button_label = $buttons[0]->getAttribute('value');
    }

    $this->submitForm($edit, $button_label);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Contains regression tests for previous, fixed bugs in the Views UI.
   */
  protected function adminUiRegressionTests() {
    $this->regressionTest2883807();
  }

  /**
   * Verifies that adding a contextual filter doesn't trigger a notice.
   *
   * @see https://www.drupal.org/node/2883807
   */
  protected function regressionTest2883807() {
    $this->drupalGet('admin/structure/views/nojs/add-handler/search_api_test_view/page_1/argument');
    $edit = [
      'name[search_api_index_database_search_index.author]' => TRUE,
    ];
    $this->submitForm($edit, 'Add and configure contextual filters');
    $this->submitForm([], 'Apply');
    $this->submitForm([], 'Save');
  }

  /**
   * Checks whether highlighting of results works correctly.
   *
   * @see views.view.search_api_test_cache.yml
   */
  public function testHighlighting() {
    // Add the Highlight processor to the search index.
    $index = Index::load('database_search_index');
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'highlight');
    $index->addProcessor($processor);
    $index->save();

    $path = 'search-api-test-search-view-caching-none';
    $this->drupalGet($path);
    $this->assertSession()->responseContains('foo bar baz');

    $options['query']['search_api_fulltext'] = 'foo';
    $this->drupalGet($path, $options);
    $this->assertSession()->responseContains('<strong>foo</strong> bar baz');

    $options['query']['search_api_fulltext'] = 'bar';
    $this->drupalGet($path, $options);
    $this->assertSession()->responseContains('foo <strong>bar</strong> baz');
  }

  /**
   * Verifies that our row plugin is available without clearing cache.
   */
  public function testCreatingIndexClearsRowPluginCache() {
    $this->drupalLogin($this->drupalCreateUser([
      'administer search_api',
      'access administration pages',
      'administer views',
    ]));

    $index_id = 'my_custom_index';
    Index::create([
      'name' => 'My custom index',
      'id' => $index_id,
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [],
        'entity:user' => [],
      ],
    ])->save();

    $this->drupalGet('/admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test view',
      'id' => 'test',
      'show[wizard_key]' => "standard:search_api_index_$index_id",
    ], 'Save and edit');

    $this->drupalGet('/admin/structure/views/nojs/display/test/default/row');
    $this->assertSession()->elementExists('css', '#edit-row-type [value="search_api"]');
  }

}

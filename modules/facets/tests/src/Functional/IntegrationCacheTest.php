<?php

namespace Drupal\Tests\facets\Functional;

use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\query_type\SearchApiDate;

/**
 * Tests facets functionality that have search_api view with search_api_cache.
 *
 * @group facets
 */
class IntegrationCacheTest extends FacetsTestBase {

  /**
   * Views view url with search_api_tag cache plugin.
   */
  protected const VIEW_URL = 'search-api-test-fulltext-cache-tag';

  /**
   * Views view display id with search_api_tag cache plugin.
   */
  protected const VIEW_DISPLAY = 'page_2_sapi_tag';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'node',
    'search_api',
    'facets',
    'block',
    'facets_search_api_dependency',
    'taxonomy',
    'page_cache',
  ];

  /**
   * Facets entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $facetStorage;

  /**
   * The entity_test_mulrev_changed entity storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $entityTestStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->enableWebsiteCache();
    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->assertEquals(5, $this->indexItems($this->indexId), '5 items were indexed.');

    $this->facetStorage = $this->container->get('entity_type.manager')
      ->getStorage('facets_facet');
    $this->entityTestStorage = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrev_changed');
  }

  /**
   * Tests various operations via the Facets' admin UI.
   *
   * Cached implementation of testBlockView integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testBlockView()
   */
  public function testFramework() {
    $facet_id = 'test_facet_name';
    $this->drupalGet(static::VIEW_URL);
    // By default, the view should show all entities.
    $this->assertSession()->pageTextContains('Displaying 5 search results');

    $this->createFacet('Test Facet name', $facet_id, 'type', static::VIEW_DISPLAY);

    // Verify that the facet results are correct.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('item');
    $this->assertSession()->pageTextContains('article');

    // Verify that facet blocks appear as expected.
    $this->assertFacetBlocksAppear();

    // Verify that the facet only shows when the facet source is visible, it
    // should not show up on the user page.
    $this->drupalGet('<front>');
    $this->assertNoFacetBlocksAppear();

    // Do not show the block on empty behaviors.
    $this->clearIndex();
    $this->drupalGet(static::VIEW_URL);

    // Verify that no facet blocks appear. Empty behavior "None" is selected by
    // default.
    $this->assertNoFacetBlocksAppear();

    // Verify that the "empty_text" appears as expected.
    $settings = [
      'behavior' => 'text',
      'text' => 'No results found for this block!',
      'text_format' => 'plain_text',
    ];
    $facet = $this->getFacetById($facet_id);
    $facet->setEmptyBehavior($settings);
    $this->facetStorage->save($facet);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->responseContains('block-test-facet-name');
    $this->assertSession()->responseContains('No results found for this block!');
  }

  /**
   * Tests that a block view also works.
   *
   * Cached implementation of testBlockView integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testBlockView()
   */
  public function testBlockView() {
    $webAssert = $this->assertSession();
    $this->createFacet(
      'Block view facet',
      'block_view_facet',
      'type',
      'block_1_sapi_tag',
      'views_block__search_api_test_view'
    );

    // Place the views block in the footer of all pages.
    $block_settings = [
      'region' => 'sidebar_first',
      'id' => 'view_block',
    ];
    $this->drupalPlaceBlock('views_block:search_api_test_view-block_1_sapi_tag', $block_settings);

    // By default, the view should show all entities.
    $this->drupalGet('<front>');
    $webAssert->pageTextContains('Fulltext test index');
    $webAssert->pageTextContains('Displaying 5 search results');
    $webAssert->pageTextContains('item');
    $webAssert->pageTextContains('article');

    // Click the item link, and test that filtering of results actually works.
    $this->clickLink('item');
    $webAssert->pageTextContains('Displaying 3 search results');
  }

  /**
   * Tests that an url alias works correctly.
   *
   * Cached implementation of testUrlAlias integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testUrlAlias()
   */
  public function testUrlAlias() {
    $facet_id = 'ab_facet';
    $this->createFacet('ab Facet', $facet_id, 'type', static::VIEW_DISPLAY);

    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    $this->clickLink('item');
    $url = Url::fromUserInput('/' . static::VIEW_URL, ['query' => ['f' => ['ab_facet:item']]]);
    $this->assertSession()->addressEquals($url);

    $this->updateFacet($facet_id, ['url_alias' => 'llama']);

    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    $this->clickLink('item');
    $url = Url::fromUserInput('/' . static::VIEW_URL, ['query' => ['f' => ['llama:item']]]);
    $this->assertSession()->addressEquals($url);
  }

  /**
   * Tests facet dependencies.
   *
   * Cached implementation of testFacetDependencies integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testFacetDependencies()
   */
  public function testFacetDependencies() {
    $facet_name = "DependableFacet";
    $facet_id = 'dependablefacet';

    $depending_facet_name = "DependingFacet";
    $depending_facet_id = "dependingfacet";

    $this->createFacet($facet_name, $facet_id, 'type', static::VIEW_DISPLAY);
    $this->createFacet($depending_facet_name, $depending_facet_id, 'keywords', static::VIEW_DISPLAY);

    // Go to the view and test that both facets are shown. Item and article
    // come from the DependableFacet, orange and grape come from DependingFacet.
    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('grape');
    $this->assertFacetLabel('orange');
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');
    $this->assertFacetBlocksAppear();

    // Change the visiblity settings of the DependingFacet.
    $facet = $this->getFacetById($depending_facet_id);
    $processor = [
      'processor_id' => 'dependent_processor',
      'weights' => ['build' => 5],
      'settings' => [
        $facet_id => [
          'enable' => TRUE,
          'condition' => 'values',
          'values' => 'item',
          'negate' => FALSE,
        ],
      ],
    ];
    $facet->addProcessor($processor);
    $this->facetStorage->save($facet);

    // Go to the view and test that only the types are shown.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->linkNotExists('grape');
    $this->assertSession()->linkNotExists('orange');
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    // Click on the item, and test that this shows the keywords.
    $this->clickLink('item');
    $this->assertFacetLabel('grape');
    $this->assertFacetLabel('orange');

    // Go back to the view, click on article and test that the keywords are
    // hidden.
    $this->drupalGet(static::VIEW_URL);
    $this->clickLink('article');
    $this->assertSession()->linkNotExists('grape');
    $this->assertSession()->linkNotExists('orange');

    // Change the visibility settings to negate the previous settings.
    $processor['settings'][$facet_id]['negate'] = TRUE;
    $facet->addProcessor($processor);
    $this->facetStorage->save($facet);

    // Go to the view and test only the type facet is shown.
    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');
    $this->assertFacetLabel('grape');
    $this->assertFacetLabel('orange');

    // Click on the article, and test that this shows the keywords.
    $this->clickLink('article');
    $this->assertFacetLabel('grape');
    $this->assertFacetLabel('orange');

    // Go back to the view, click on item and test that the keywords are
    // hidden.
    $this->drupalGet(static::VIEW_URL);
    $this->clickLink('item');
    $this->assertSession()->linkNotExists('grape');
    $this->assertSession()->linkNotExists('orange');

    // Disable negation again.
    $processor['settings'][$facet_id]['negate'] = FALSE;
    $facet->addProcessor($processor);
    $this->facetStorage->save($facet);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertSession()->linkNotExists('grape');
    $this->clickLink('item');
    $this->assertSession()->pageTextContains('Displaying 3 search results');
    $this->assertSession()->linkExists('grape');
    $this->clickLink('grape');
    $this->assertSession()->pageTextContains('Displaying 1 search results');
    // Disable item again, and the grape should not be reflected in the search
    // result anymore.
    $this->clickLink('item');
    $this->assertSession()->pageTextContains('Displaying 5 search results');
  }

  /**
   * Tests the facet's and/or functionality.
   *
   * Cached implementation of testAndOrFacet integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testAndOrFacet()
   */
  public function testAndOrFacet() {
    $facet_id = 'test_facet';

    $this->createFacet('test & facet', $facet_id, 'type', static::VIEW_DISPLAY);
    $this->updateFacet($facet_id, ['query_operator' => 'and']);

    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    $this->clickLink('item');
    $this->checkFacetIsActive('item');
    $this->assertSession()->linkNotExists('article');

    $this->updateFacet($facet_id, ['query_operator' => 'or']);

    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetLabel('item');
    $this->assertFacetLabel('article');

    $this->clickLink('item (3)');
    $this->checkFacetIsActive('item');
    $this->assertFacetLabel('article (2)');
  }

  /**
   * Tests the facet's exclude functionality.
   *
   * Cached implementation of testExcludeFacet integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testExcludeFacet()
   */
  public function testExcludeFacet() {
    $facet_id = 'test_facet';
    $this->createFacet('test & facet', $facet_id, 'type', static::VIEW_DISPLAY);
    $this->updateFacet($facet_id, ['exclude' => TRUE]);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('foo bar baz');
    $this->assertSession()->pageTextContains('foo baz');
    $this->assertFacetLabel('item');

    $this->clickLink('item');
    $this->checkFacetIsActive('item');
    $this->assertSession()->pageTextContains('foo baz');
    $this->assertSession()->pageTextContains('bar baz');
    $this->assertSession()->pageTextNotContains('foo bar baz');

    $this->updateFacet($facet_id, ['exclude' => FALSE]);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('foo bar baz');
    $this->assertSession()->pageTextContains('foo baz');
    $this->assertFacetLabel('item');

    $this->clickLink('item');
    $this->checkFacetIsActive('item');
    $this->assertSession()->pageTextContains('foo bar baz');
    $this->assertSession()->pageTextContains('foo test');
    $this->assertSession()->pageTextContains('bar');
    $this->assertSession()->pageTextNotContains('foo baz');
  }

  /**
   * Tests the facet's exclude functionality for a date field.
   *
   * Cached implementation of testExcludeFacetDate integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testExcludeFacetDate()
   */
  public function testExcludeFacetDate() {
    $facet_id = $field_name = 'created';

    $this->entityTestStorage->create([
      'name' => 'foo new',
      'body' => 'test test',
      'type' => 'item',
      'keywords' => ['orange'],
      'category' => 'item_category',
      $field_name => 1490000000,
    ])->save();

    $this->entityTestStorage->create([
      'name' => 'foo old',
      'body' => 'test test',
      'type' => 'item',
      'keywords' => ['orange'],
      'category' => 'item_category',
      $field_name => 1460000000,
    ])->save();

    $this->assertEquals(2, $this->indexItems($this->indexId), '2 items were indexed.');

    $this->createFacet('Created', $facet_id, $field_name, static::VIEW_DISPLAY);
    $facet = $this->getFacetById($facet_id);
    $facet->addProcessor([
      'processor_id' => 'date_item',
      'weights' => ['build' => 35],
      'settings' => [
        'date_display' => 'actual_date',
        'granularity' => SearchApiDate::FACETAPI_DATE_MONTH,
        'hierarchy' => FALSE,
        'date_format' => '',
      ],
    ]);
    $this->facetStorage->save($facet);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('foo old');
    $this->assertSession()->pageTextContains('foo new');
    $this->clickLink('March 2017');
    $this->checkFacetIsActive('March 2017');
    $this->assertSession()->pageTextContains('foo new');
    $this->assertSession()->pageTextNotContains('foo old');

    $this->updateFacet($facet->id(), ['exclude' => TRUE]);

    $this->drupalGet(static::VIEW_URL);
    $this->clickLink('March 2017');
    $this->checkFacetIsActive('March 2017');
    $this->assertSession()->pageTextContains('foo old');
    $this->assertSession()->pageTextNotContains('foo new');
  }

  /**
   * Tests allow only one active item.
   *
   * Cached implementation of testAllowOneActiveItem integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testAllowOneActiveItem()
   */
  public function testAllowOneActiveItem() {
    $this->createFacet('Spotted wood owl', 'spotted_wood_owl', 'keywords', static::VIEW_DISPLAY);

    $facet = $this->getFacetById('spotted_wood_owl');
    $facet->setShowOnlyOneResult(TRUE);
    $this->facetStorage->save($facet);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('grape');
    $this->assertFacetLabel('orange');

    $this->clickLink('grape');
    $this->assertSession()->pageTextContains('Displaying 3 search results');
    $this->checkFacetIsActive('grape');
    $this->assertFacetLabel('orange');

    $this->clickLink('orange');
    $this->assertSession()->pageTextContains('Displaying 3 search results');
    $this->assertFacetLabel('grape');
    $this->checkFacetIsActive('orange');
  }

  /**
   * Tests calculations of facet count.
   *
   * Cached implementation of testFacetCountCalculations integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testFacetCountCalculations()
   */
  public function testFacetCountCalculations() {
    $this->createFacet('Type', 'type', 'type', static::VIEW_DISPLAY);
    $this->createFacet('Keywords', 'keywords', 'keywords', static::VIEW_DISPLAY);
    foreach (['type', 'keywords'] as $facet_id) {
      $this->updateFacet($facet_id, ['query_operator' => 'and']);
    }

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('article (2)');
    $this->assertFacetLabel('grape (3)');

    // Make sure that after clicking on article, which has only 2 entities,
    // there are only 2 items left in the results for other facets as well.
    // In this case, that means we can't have 3 entities tagged with grape. Both
    // remaining entities are tagged with grape and strawberry.
    $this->clickPartialLink('article');
    $this->assertSession()->pageTextNotContains('(3)');
    $this->assertFacetLabel('grape (2)');
    $this->assertFacetLabel('strawberry (2)');

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('article (2)');
    $this->assertFacetLabel('grape (3)');

    // Make sure that after clicking on grape, which has only 3 entities, there
    // are only 3 items left in the results for other facets as well. In this
    // case, that means 2 entities of type article and 1 item.
    $this->clickPartialLink('grape');
    $this->assertSession()->pageTextContains('Displaying 3 search results');
    $this->assertFacetLabel('article (2)');
    $this->assertFacetLabel('item (1)');
  }

  /**
   * Tests the hard limit setting.
   *
   * Cached implementation of testHardLimit integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testHardLimit()
   */
  public function testHardLimit() {
    $this->createFacet('Owl', 'owl', 'keywords', static::VIEW_DISPLAY);
    $facet = $this->getFacetById('owl');
    $facet->addProcessor([
      'processor_id' => 'active_widget_order',
      'weights' => ['sort' => 20],
      'settings' => [],
    ]);
    $facet->addProcessor([
      'processor_id' => 'display_value_widget_order',
      'weights' => ['build' => 40],
      'settings' => [],
    ]);
    $this->facetStorage->save($facet);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('grape (3)');
    $this->assertFacetLabel('orange (3)');
    $this->assertFacetLabel('apple (2)');
    $this->assertFacetLabel('banana (1)');
    $this->assertFacetLabel('strawberry (2)');

    $this->updateFacet($facet->id(), ['hard_limit' => 3]);

    $this->drupalGet(static::VIEW_URL);
    // We're still testing for 5 search results here, the hard limit only limits
    // the facets, not the search results.
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('grape (3)');
    $this->assertFacetLabel('orange (3)');
    $this->assertFacetLabel('apple (2)');
    $this->assertSession()->pageTextNotContains('banana (0)');
    $this->assertSession()->pageTextNotContains('strawberry (0)');
  }

  /**
   * Test minimum amount of items.
   *
   * Cached implementation of testMinimumAmount integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testMinimumAmount()
   */
  public function testMinimumAmount() {
    $this->createFacet('Elf owl', 'elf_owl', 'type', static::VIEW_DISPLAY);
    $this->updateFacet('elf_owl', ['min_count' => 1]);

    // See that both article and item are showing.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('article (2)');
    $this->assertFacetLabel('item (3)');

    $this->updateFacet('elf_owl', ['min_count' => 3]);

    // See that article is now hidden, item should still be showing.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertSession()->pageTextNotContains('article');
    $this->assertFacetLabel('item (3)');
  }

  /**
   * Tests the visibility of facet source.
   *
   * Cached implementation of testFacetSourceVisibility integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testFacetSourceVisibility()
   */
  public function testFacetSourceVisibility() {
    $this->createFacet('Vicuña', 'vicuna', 'type', static::VIEW_DISPLAY);
    // Facet appears only on the search page for which it was created.
    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetBlocksAppear();
    $this->drupalGet('');
    $this->assertNoFacetBlocksAppear();

    $facet = $this->getFacetById('vicuna');
    $facet->setOnlyVisibleWhenFacetSourceIsVisible(FALSE);
    $this->facetStorage->save($facet);

    // Test that the facet source is visible on the search page and user/2 page.
    $this->drupalGet(static::VIEW_URL);
    $this->assertFacetBlocksAppear();
    $this->drupalGet('');
    $this->assertFacetBlocksAppear();
  }

  /**
   * Tests behavior with multiple enabled facets and their interaction.
   *
   * Cached implementation of testMultipleFacets integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testMultipleFacets()
   */
  public function testMultipleFacets() {
    // Create 2 facets.
    $this->createFacet('Snow Owl', 'snow_owl', 'type', static::VIEW_DISPLAY);
    $this->createFacet('Forest Owl', 'forest_owl', 'category', static::VIEW_DISPLAY);

    foreach (['snow_owl', 'forest_owl'] as $facet_id) {
      $this->updateFacet($facet_id, ['min_count' => 0]);
    }

    // Go to the view and check the default behavior.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->assertFacetLabel('item (3)');
    $this->assertFacetLabel('article (2)');
    $this->assertFacetLabel('item_category (2)');
    $this->assertFacetLabel('article_category (2)');

    // Start filtering.
    $this->clickPartialLink('item_category');
    $this->assertSession()->pageTextContains('Displaying 2 search results');
    $this->checkFacetIsActive('item_category');
    $this->assertFacetLabel('item (2)');

    // Go back to the overview and start another filter, from the second facet
    // block this time.
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextContains('Displaying 5 search results');
    $this->clickPartialLink('article (2)');
    $this->assertSession()->pageTextContains('Displaying 2 search results');
    $this->checkFacetIsActive('article');
    $this->assertFacetLabel('article_category (2)');
    $this->assertFacetLabel('item_category (0)');
  }

  /**
   * Tests that the configuration for showing a title works.
   *
   * Cached implementation of testShowTitle integration test.
   *
   * @see \Drupal\Tests\facets\Functional\IntegrationTest::testShowTitle()
   */
  public function testShowTitle() {
    $this->createFacet('Llama', 'llama', 'type', static::VIEW_DISPLAY);
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->pageTextNotContains('Llama');

    $this->updateFacet('llama', ['show_title' => TRUE]);

    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()->responseContains('<h3>Llama</h3>');
    $this->assertSession()->pageTextContains('Llama');
  }

  /**
   * Test facet blocks cache invalidation.
   *
   * Test covers search page with a facets and standalone facet block on FP.
   */
  public function testFacetBlockCacheNewContentIndexing() {
    $this->createFacet('Test Facet name', 'test_facet_name', 'type', static::VIEW_DISPLAY);

    $facet = $this->getFacetById('test_facet_name');
    $facet->setOnlyVisibleWhenFacetSourceIsVisible(FALSE);
    $this->facetStorage->save($facet);

    foreach (['', static::VIEW_URL] as $url) {
      $this->drupalGet($url);
      $this->assertFacetLabel('article (2)');
      $this->assertFacetLabel('item (3)');
    }

    $this->entityTestStorage->create([
      'name' => 'foo jiz baz',
      'body' => 'test test and a bit more test',
      'type' => 'item',
      'keywords' => ['orange', 'black'],
      'category' => 'item_category',
    ])->save();

    // Entity was added but not indexed yet, so facet state should remain the
    // same.
    foreach (['', static::VIEW_URL] as $url) {
      $this->drupalGet($url);
      $this->assertFacetLabel('article (2)');
      $this->assertFacetLabel('item (3)');
    }

    // Index 1 remaining item and check that count has been updated.
    $this->assertEquals(1, $this->indexItems($this->indexId), '1 item was indexed.');
    foreach (['', static::VIEW_URL] as $url) {
      $this->drupalGet($url);
      $this->assertFacetLabel('article (2)');
      $this->assertFacetLabel('item (4)');
    }
  }

  /**
   * Enable website page caching, set 1 day max age.
   */
  protected function enableWebsiteCache() {
    $max_age = 86400;
    $this->config('system.performance')
      ->set('cache.page.max_age', $max_age)
      ->save();
    $this->drupalGet(static::VIEW_URL);
    $this->assertSession()
      ->responseHeaderContains('Cache-Control', 'max-age=' . $max_age);
  }

  /**
   * Get facet entity by ids.
   *
   * @param string $id
   *   Facet id.
   *
   * @return \Drupal\facets\FacetInterface
   *   Loaded facet object.
   */
  protected function getFacetById(string $id): FacetInterface {
    return $this->facetStorage->load($id);
  }

  /**
   * Update facet tith with given values.
   *
   * @param string $id
   *   The facet entity ID.
   * @param array $settings
   *   Array with values keyed  by property names.
   *
   * @return \Drupal\facets\FacetInterface
   *   An updated facet entity.
   */
  protected function updateFacet(string $id, array $settings): FacetInterface {
    $facet = $this->getFacetById($id);
    foreach ($settings as $name => $value) {
      $facet->set($name, $value);
    }
    $this->facetStorage->save($facet);

    return $facet;
  }

}

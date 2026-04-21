<?php

namespace Drupal\Tests\facets_exposed_filters\Functional;

use Drupal\Tests\facets\Functional\FacetsTestBase;

/**
 * Tests the overall functionality of the Facets admin UI.
 *
 * @group facets
 */
class ExposedFiltersTest extends FacetsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_ui',
    'node',
    'search_api',
    'facets',
    'facets_exposed_filters',
    'facets_exposed_filters_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);

    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->assertEquals(5, $this->indexItems($this->indexId), '5 items were indexed.');
  }

  /**
   * Tests slider widget.
   */
  public function testExposedFilters() {
    // Test non-filtered page.
    $this->drupalGet('test-facets-exposed-filters');
    $this->assertSession()->pageTextContains('Keywords');
    $this->assertSession()->pageTextContains('entity:entity_test_mulrev_changed/3:en');
    $this->assertSession()->pageTextContains('strawberry');

    // Test filtered page.
    $this->drupalGet('test-facets-exposed-filters', ['query' => ['keywords[]' => 'apple']]);
    $this->assertSession()->pageTextContains('Keywords');
    $this->assertSession()->pageTextNotContains('entity:entity_test_mulrev_changed/3:en');
    $this->assertSession()->pageTextContains('strawberry');

    // Test if facet item disappears when non-matching category is selected.
    $this->drupalGet('test-facets-exposed-filters', ['query' => ['category[]' => 'item_category']]);
    $this->assertSession()->pageTextContains('Keywords');
    $this->assertSession()->pageTextNotContains('strawberry');

    // Test if facet item remains when matching category is selected.
    $this->drupalGet('test-facets-exposed-filters', ['query' => ['category[]' => 'article_category']]);
    $this->assertSession()->pageTextContains('Keywords');
    $this->assertSession()->pageTextContains('strawberry');
  }

}

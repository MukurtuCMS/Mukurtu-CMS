<?php

namespace Drupal\Tests\facets\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetSourceInterface;
use Drupal\views\Views;

/**
 * Tests the overall functionality of the Facets admin UI.
 *
 * @group facets
 */
class UrlIntegrationTest extends FacetsTestBase {

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
    'facets_query_processor',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->assertEquals($this->indexItems($this->indexId), 5, '5 items were indexed.');
  }

  /**
   * Tests various url integration things.
   */
  public function testUrlIntegration() {
    $id = 'facet';
    $name = '&^Facet@#1';
    $this->createFacet($name, $id);

    $url = Url::fromUserInput('/search-api-test-fulltext', ['query' => ['f[0]' => 'facet:item']]);
    $this->clickFacet();
    $this->assertSession()->addressEquals($url);

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = Facet::load($id);
    $this->assertInstanceOf(FacetInterface::class, $facet);
    $config = $facet->getFacetSourceConfig();
    $this->assertInstanceOf(FacetSourceInterface::class, $config);
    $this->assertEquals('f', $config->getFilterKey());

    $facet = NULL;
    $config = NULL;

    // Go to the only enabled facet source's config and change the filter key.
    $this->drupalGet('admin/config/search/facets');
    $this->clickLink('Configure', 2);

    $edit = [
      'filter_key' => 'y',
      'url_processor' => 'query_string',
    ];
    $this->submitForm($edit, 'Save');

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = Facet::load($id);
    $config = $facet->getFacetSourceConfig();
    $this->assertInstanceOf(FacetSourceInterface::class, $config);
    $this->assertEquals('y', $config->getFilterKey());

    $facet = NULL;
    $config = NULL;

    $url_2 = Url::fromUserInput('/search-api-test-fulltext', ['query' => ['y[0]' => 'facet:item']]);
    $this->clickFacet();
    $this->assertSession()->addressEquals($url_2);

    // Go to the only enabled facet source's config and change the url
    // processor.
    $this->drupalGet('admin/config/search/facets');
    $this->clickLink('Configure', 2);

    $edit = [
      'filter_key' => 'y',
      'url_processor' => 'dummy_query',
    ];
    $this->submitForm($edit, 'Save');

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = Facet::load($id);
    $config = $facet->getFacetSourceConfig();
    $this->assertInstanceOf(FacetSourceInterface::class, $config);
    $this->assertEquals('y', $config->getFilterKey());

    $facet = NULL;
    $config = NULL;

    $url_3 = Url::fromUserInput('/search-api-test-fulltext', ['query' => ['y[0]' => 'facet||item']]);
    $this->clickFacet();
    $this->assertSession()->addressEquals($url_3);
  }

  /**
   * Tests the url when a colon is used in the value.
   */
  public function testColonValue() {
    $id = 'water_bear';
    $name = 'Water bear';
    $this->createFacet($name, $id, 'keywords');

    // Add a new entity that has a colon in one of it's keywords.
    $entity_test_storage = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrev_changed');
    $entity_test_storage->create([
      'name' => 'Entity with colon',
      'body' => 'test test',
      'type' => 'item',
      'keywords' => ['orange', 'test:colon'],
      'category' => 'item_category',
    ])->save();
    // Make sure the new item is indexed.
    $this->assertEquals(1, $this->indexItems($this->indexId));

    // Go to the overview and test that we have the expected links.
    $this->drupalGet('search-api-test-fulltext');
    $this->assertFacetLabel('test:colon');
    $this->assertFacetLabel('orange');
    $this->assertFacetLabel('banana');

    // Click the link with the colon.
    $this->clickLink('test:colon');
    $this->assertSession()->statusCodeEquals(200);

    // Make sure 'test:colon' is active.
    $url = Url::fromUserInput('/search-api-test-fulltext', ['query' => ['f[0]' => 'water_bear:test:colon']]);
    $this->assertSession()->addressEquals($url);
    $this->checkFacetIsActive('test:colon');
    $this->assertFacetLabel('orange');
    $this->assertFacetLabel('banana');
  }

  /**
   * Regression test for #2871475.
   *
   * @link https://drupal.org/node/2871475
   */
  public function testIncompleteFacetUrl() {
    $id = 'owl';
    $name = 'Owl';
    $this->createFacet($name, $id);

    $url = Url::fromUserInput('/search-api-test-fulltext');
    $this->clickFacet();
    $this->assertSession()->addressEquals($url);

    // Build the path as described in #2871475.
    $path = 'search-api-test-fulltext';
    $options['absolute'] = TRUE;
    $url = $this->buildUrl($path, $options);
    $url .= '?f';

    // Visit the page.
    $session = $this->getSession();
    $this->prepareRequest();
    $session->visit($url);

    // Check that no errors occurred.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Regression test for #2898189.
   *
   * @link https://www.drupal.org/node/2898189
   */
  public function testResetPager() {
    $id = 'owl';
    $name = 'Owl';
    $this->createFacet($name, $id);

    // Set view pager option to 2 items, so we can check the pager rest on the
    // facet links.
    $view = Views::getView('search_api_test_view');
    $view->setDisplay('page_1');
    $pagerOptions = $view->display_handler->getOption('pager');
    $pagerOptions['options']['items_per_page'] = 2;
    $view->display_handler->setOption('pager', $pagerOptions);
    $view->save();

    $content_types = ['item', 'article'];
    foreach ($content_types as $content_type) {
      $this->drupalGet('search-api-test-fulltext');
      $this->clickLink('2');
      $this->assertNotFalse(strpos($this->getUrl(), 'page=1'));
      $this->clickLink($content_type);
      $this->assertFalse(strpos($this->getUrl(), 'page=1'));
    }
  }

  /**
   * Tests that creating a facet with a duplicate url alias emits a warning.
   */
  public function testCreatingDuplicateUrlAlias() {
    $this->createFacet('Owl', 'owl');
    $this->createFacet('Another owl', 'another_owl');
    $this->drupalGet('admin/config/search/facets/another_owl/edit');
    $this->submitForm(['facet_settings[url_alias]' => 'owl'], 'Save');
    $this->assertSession()->pageTextContains('This alias is already in use for another facet defined on the same source.');
  }

  /**
   * Tests that modules can change the facet url.
   */
  public function testFacetUrlCanBeChanged() {
    $modules = ['facets_events_test'];
    $success = $this->container->get('module_installer')->install($modules, TRUE);
    $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => implode(', ', $modules)]));
    $this->rebuildAll();

    $id = 'facet';
    $name = 'Facet';
    $this->createFacet($name, $id);

    $this->clickFacet();
    $url = urldecode($this->getSession()->getCurrentUrl());
    $this->assertStringContainsString('test=fun', $url);
    $this->assertStringContainsString('f[0]=facet:item', $url);
  }

}

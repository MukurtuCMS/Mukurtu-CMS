<?php

declare(strict_types=1);

namespace Drupal\Tests\facets_searchbox_widget\FunctionalJavascript;

use Drupal\Tests\facets\FunctionalJavascript\WidgetJSTest;

/**
 * Tests for the JS that transforms widgets into form elements.
 *
 * @group facets
 */
class SearchboxWidgetJSTest extends WidgetJSTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'search_api',
    'facets',
    'facets_search_api_dependency',
    'facets_searchbox_widget',
    'block',
  ];

  /**
   * Tests searchbox for links.
   */
  public function testSearchboxLinks() {
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');
    $id = 'sl';

    // Create and save a facet with a checkbox widget on the 'type' field.
    $facet_storage->create([
      'id' => $id,
      'name' => strtoupper($id),
      'url_alias' => $id,
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
      'field_identifier' => 'type',
      'empty_behavior' => ['behavior' => 'none'],
      'weight' => 1,
      'widget' => [
        'type' => 'searchbox_links',
      ],
      'processor_configs' => [
        'url_processor_handler' => [
          'processor_id' => 'url_processor_handler',
          'weights' => ['pre_query' => -10, 'build' => -10],
          'settings' => [],
        ],
      ],
    ])->save();
    $this->createBlock($id);

    // Go to the views page.
    $this->drupalGet('search-api-test-fulltext');

    // Make sure the block is shown on the page.
    $page = $this->getSession()->getPage();
    $block = $page->findById('block-sl-block');
    $block->isVisible();

    // Make sure the searchbox input exists.
    $this->assertSession()->elementExists('css', '.facets-widget-searchbox');
    // Make sure the searchbox link list exists.
    $this->assertSession()->elementExists('css', '.facets-widget-searchbox-list');
  }

  /**
   * Tests searchbox for checkbox widget.
   */
  public function testSearchboxCheckboxWidget() {
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');
    $id = 'sc';

    // Create and save a facet with a checkbox widget on the 'type' field.
    $facet_storage->create([
      'id' => $id,
      'name' => strtoupper($id),
      'url_alias' => $id,
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
      'field_identifier' => 'type',
      'empty_behavior' => ['behavior' => 'none'],
      'widget' => [
        'type' => 'searchbox_checkbox',
        'config' => [
          'show_numbers' => TRUE,
        ],
      ],
      'processor_configs' => [
        'url_processor_handler' => [
          'processor_id' => 'url_processor_handler',
          'weights' => ['pre_query' => -10, 'build' => -10],
          'settings' => [],
        ],
      ],
    ])->save();
    $this->createBlock($id);

    // Go to the views page.
    $this->drupalGet('search-api-test-fulltext');

    // Make sure the block is shown on the page.
    $page = $this->getSession()->getPage();
    $block = $page->findById('block-sc-block');
    $this->assertTrue($block->isVisible());

    // Make sure the searchbox input exists.
    $this->assertSession()->elementExists('css', '.facets-widget-searchbox');
    // Make sure the searchbox link list exists.
    $this->assertSession()->elementExists('css', '.facets-widget-searchbox-list');
  }

}

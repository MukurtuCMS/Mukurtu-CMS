<?php

declare(strict_types=1);

namespace Drupal\Tests\facets\FunctionalJavascript;

use Drupal\facets\Entity\Facet;

/**
 * Tests for the JS that transforms widgets into form elements.
 *
 * @group facets
 */
class WidgetJSTest extends JsBase {

  /**
   * Tests show more / less links.
   */
  public function testLinksShowMoreLess() {
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');
    $id = 'owl';

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
        'type' => 'links',
        'config' => [
          'show_numbers' => TRUE,
          'soft_limit' => 1,
          'soft_limit_settings' => [
            'show_less_label' => 'Show less',
            'show_more_label' => 'Show more',
          ],
        ],
      ],
      'processor_configs' => [
        'url_processor_handler' => [
          'processor_id' => 'url_processor_handler',
          'weights' => ['pre_query' => -10, 'build' => -10],
          'settings' => [],
        ],
      ],
      'use_hierarchy' => FALSE,
      'hierarchy' => ['type' => 'taxonomy', 'config' => []],
    ])->save();
    $this->createBlock($id);

    // Go to the views page.
    $this->drupalGet('search-api-test-fulltext');

    // Make sure the block is shown on the page.
    $page = $this->getSession()->getPage();
    $block = $page->findById('block-owl-block');
    $block->isVisible();

    // Make sure the show more / show less links are shown.
    $this->assertSession()->linkExists('Show more');

    // Change the link label of show more into "Moar Llamas".
    $facet = Facet::load('owl');
    $facet->setWidget('links', [
      'show_numbers' => TRUE,
      'soft_limit' => 1,
      'soft_limit_settings' => [
        'show_less_label' => 'Show less',
        'show_more_label' => 'Moar Llamas',
      ],
    ]);
    $facet->save();

    // Check that the new configuration is used now.
    $this->drupalGet('search-api-test-fulltext');
    $this->assertSession()->linkNotExists('Show more');
    $this->assertSession()->linkExists('Moar Llamas');
  }

  /**
   * Tests checkbox widget.
   */
  public function testCheckboxWidget() {
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');
    $id = 'llama';

    // Create and save a facet with a checkbox widget on the 'type' field.
    $facet_storage->create([
      'id' => $id,
      'name' => strtoupper($id),
      'url_alias' => $id,
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
      'field_identifier' => 'type',
      'empty_behavior' => ['behavior' => 'none'],
      'widget' => [
        'type' => 'checkbox',
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
      'use_hierarchy' => FALSE,
      'hierarchy' => ['type' => 'taxonomy', 'config' => []],
    ])->save();
    $this->createBlock($id);

    // Go to the views page.
    $this->drupalGet('search-api-test-fulltext');

    // Make sure the block is shown on the page.
    $page = $this->getSession()->getPage();
    $block = $page->findById('block-llama-block');
    $this->assertTrue($block->isVisible());

    // The checkboxes should be wrapped in a container with a CSS class that
    // correctly identifies the widget type.
    $this->assertCount(1, $block->findAll('css', 'div.facets-widget-checkbox ul'));

    // The checkboxes should be wrapped in a list element that has the expected
    // CSS classes to identify it as well as the data attributes that enable the
    // JS functionality.
    $this->assertCount(1, $block->findAll('css', 'ul.facet-inactive.item-list__checkbox.js-facets-widget.js-facets-checkbox-links'));
    $this->assertCount(1, $block->findAll('css', 'ul[data-drupal-facet-id="llama"]'));
    $this->assertCount(1, $block->findAll('css', 'ul[data-drupal-facet-alias="llama"]'));

    // There should be two list items that can be identified by CSS class.
    $list_items = $block->findAll('css', 'ul li.facet-item');
    $this->assertCount(2, $list_items);

    // The list items should contain a checkbox, a label and a hidden link that
    // leads to the updated search results. None of the checkboxes should be
    // checked.
    $expected = [
      [
        'item',
        3,
        base_path() . 'search-api-test-fulltext?f%5B0%5D=llama%3Aitem',
        FALSE,
      ],
      [
        'article',
        2,
        base_path() . 'search-api-test-fulltext?f%5B0%5D=llama%3Aarticle',
        FALSE,
      ],
    ];
    $this->assertListItems($expected, $list_items);

    // Checking one of the checkboxes should cause a redirect to a page with
    // updated search results.
    $checkbox = $page->findField('item (3)');
    $checkbox->click();
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('search-api-test-fulltext?f%5B0%5D=llama%3Aitem', $current_url);

    // Now the chosen keyword should be checked and the hidden links should be
    // updated.
    $expected = [
      [
        'item',
        3,
        base_path() . 'search-api-test-fulltext',
        TRUE,
      ],
      [
        'article',
        2,
        base_path() . 'search-api-test-fulltext?f%5B0%5D=llama%3Aarticle',
        FALSE,
      ],
    ];
    $this->assertListItems($expected, $block->findAll('css', 'ul li.facet-item'));

    // Unchecking a checkbox should remove the keyword from the search.
    $checkbox = $page->findField('item (3)');
    $checkbox->click();
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('search-api-test-fulltext', $current_url);
    $expected = [
      [
        'item',
        3,
        base_path() . 'search-api-test-fulltext?f%5B0%5D=llama%3Aitem',
        FALSE,
      ],
      [
        'article',
        2,
        base_path() . 'search-api-test-fulltext?f%5B0%5D=llama%3Aarticle',
        FALSE,
      ],
    ];
    $this->assertListItems($expected, $block->findAll('css', 'ul li.facet-item'));
  }

  /**
   * Checks that the list items that wrap checkboxes are rendered correctly.
   *
   * @param array[] $expected
   *   An array of expected properties, each an array with the following values:
   *   - The expected checkbox value.
   *   - The expected number of results, displayed in the checkbox label.
   *   - The URI leading to the updated search results.
   *   - A boolean indicating whether the checkbox is expected to be checked.
   * @param \Behat\Mink\Element\NodeElement[] $list_items
   *   The list items to check.
   */
  protected function assertListItems(array $expected, array $list_items): void {
    $this->assertCount(count($expected), $list_items);

    foreach ($expected as $key => [$keyword, $count, $uri, $selected]) {
      $list_item = $list_items[$key];

      // The list element should be visible.
      $this->assertTrue($list_item->isVisible());

      // It should contain 1 input element (the checkbox). It should have the
      // expected ID and CSS class.
      $item_id = "llama-{$keyword}";
      $this->assertCount(1, $list_item->findAll('css', 'input'));
      $this->assertCount(1, $list_item->findAll('css', "input#{$item_id}[type='checkbox'].facets-checkbox"));

      // It should contain a label for the checkbox.
      $labels = $list_item->findAll('css', "label[for=$item_id]");
      $this->assertCount(1, $labels);
      // The label should contain the search keyword and the result count. Since
      // there can be multiple spaces or newlines between the keyword and the
      // count, reduce them to a single space before asserting. The keyword and
      // the count should be wrapped in elements with semantic classes.
      $label = reset($labels);
      $expected_text = "<span class=\"facet-item__value\">$keyword</span> <span class=\"facet-item__count\">($count)</span>";
      $this->assertTrue($label->isVisible());
      $this->assertEquals($expected_text, trim(preg_replace('/\s+/', ' ', $label->getHtml())));

      // There should be a hidden link that leads to the updated search results.
      // If a user checks a checkbox this hidden link is followed in JS.
      $links = $list_item->findAll('css', 'a');
      $this->assertCount(1, $links);
      $link = reset($links);
      // The link should not be visible.
      $this->assertFalse($link->isVisible());
      // The link should indicate that search engines shouldn't follow it.
      $this->assertEquals('nofollow', $link->getAttribute('rel'));
      // The link should have CSS classes that allow to attach our JS code.
      $this->assertEquals($item_id, $link->getAttribute('data-drupal-facet-item-id'));
      $this->assertEquals($keyword, $link->getAttribute('data-drupal-facet-item-value'));
      // The link text should include the keyword as well as the count.
      $this->assertStringContainsString($expected_text, trim(preg_replace('/\s+/', ' ', $link->getHtml())));
    }
  }

  /**
   * Tests dropdown widget.
   */
  public function testDropdownWidget() {
    $facet_storage = \Drupal::entityTypeManager()->getStorage('facets_facet');
    $id = 'llama';

    // Create and save a facet with a checkbox widget on the 'type' field.
    $facet_storage->create([
      'id' => $id,
      'name' => strtoupper($id),
      'url_alias' => $id,
      'facet_source_id' => 'search_api:views_page__search_api_test_view__page_1',
      'field_identifier' => 'type',
      'empty_behavior' => ['behavior' => 'none'],
      'show_only_one_result' => TRUE,
      'widget' => [
        'type' => 'dropdown',
        'config' => [
          'show_numbers' => TRUE,
          'default_option_label' => '- All -',
        ],
      ],
      'processor_configs' => [
        'url_processor_handler' => [
          'processor_id' => 'url_processor_handler',
          'weights' => ['pre_query' => -10, 'build' => -10],
          'settings' => [],
        ],
      ],
      'use_hierarchy' => FALSE,
      'hierarchy' => ['type' => 'taxonomy', 'config' => []],
    ])->save();
    $this->createBlock($id);

    // Go to the views page.
    $this->drupalGet('search-api-test-fulltext');

    // Make sure the block is shown on the page.
    $page = $this->getSession()->getPage();
    $block = $page->findById('block-llama-block');
    $this->assertTrue($block->isVisible());

    // There should be a single select element in the block.
    $this->assertCount(1, $block->findAll('css', 'select'));

    // The select element should be wrapped in a container with a CSS class that
    // correctly identifies the widget type.
    $this->assertCount(1, $block->findAll('css', 'div.facets-widget-dropdown select'));

    // The select element should have the expected CSS classes to identify it as
    // well as the data attributes that enable the JS functionality.
    $this->assertCount(1, $block->findAll('css', 'select.facet-inactive.item-list__dropdown.facets-dropdown.js-facets-widget.js-facets-dropdown'));
    $this->assertCount(1, $block->findAll('css', 'select[data-drupal-facet-id="llama"]'));
    $this->assertCount(1, $block->findAll('css', 'select[data-drupal-facet-alias="llama"]'));

    // The select element should have an accessible label.
    $this->assertCount(1, $block->findAll('css', 'select[aria-labelledby="facet_llama_label"]'));
    $this->assertCount(1, $block->findAll('css', 'label#facet_llama_label'));
    $this->assertEquals('Facet LLAMA', $block->find('css', 'label')->getHtml());

    // The select element should be visible.
    $dropdown = $block->find('css', 'select');
    $this->assertTrue($dropdown->isVisible());

    // There should be 3 options in the expected order.
    $options = $dropdown->findAll('css', 'option');

    $expected = [
      // The first option is the default option, it doesn't have a value and it
      // should be selected.
      [
        '- All -',
        '',
        TRUE,
      ],
      [
        'item (3)',
        'llama:item',
        FALSE,
      ],
      [
        'article (2)',
        'llama:article',
        FALSE,
      ],
    ];

    $this->assertSelectOptions($expected, $options);

    // Selecting one of the options should cause a redirect to a page with
    // updated search results.
    $dropdown->selectOption('item (3)');
    $this->getSession()->wait(6000, "window.location.search != ''");
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('search-api-test-fulltext?f%5B0%5D=llama%3Aitem', $current_url);

    // Now the clicked option should be selected and the URIs in the option
    // values should be updated.
    $dropdown = $block->find('css', 'select');
    $this->assertTrue($dropdown->isVisible());
    $options = $dropdown->findAll('css', 'option');

    $expected = [
      // The first option is the default option, it should point to the original
      // search result (without any chosen facets) and should not be selected.
      [
        '- All -',
        base_path() . 'search-api-test-fulltext',
        FALSE,
      ],
      [
        'item (3)',
        'llama:item',
        TRUE,
      ],
      [
        'article (2)',
        'llama:article',
        FALSE,
      ],
    ];
    $this->assertSelectOptions($expected, $options);
  }

  /**
   * Checks that the given select option elements have the selected properties.
   *
   * @param array[] $expected
   *   An array of expected properties, each an array with the following values:
   *   - The expected option text.
   *   - The expected option value.
   *   - A boolean indicating whether the option is expected to be selected.
   * @param \Behat\Mink\Element\NodeElement[] $options
   *   The list of options to check.
   */
  protected function assertSelectOptions(array $expected, array $options): void {
    $this->assertCount(count($expected), $options);
    foreach ($expected as $key => [$text, $value, $selected]) {
      $option = $options[$key];
      // There can be multiple spaces or newlines between the value text and the
      // number of results. Reduce them to a single space before asserting.
      $this->assertEquals($text, trim(preg_replace('/\s+/', ' ', $option->getText())));
      $this->assertEquals($value, $option->getValue());
      $this->assertEquals($selected, $option->isSelected());
    }
  }

}

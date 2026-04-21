<?php

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Tests the advanced options of a filter widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase
 */
class FilterWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests sorting filter options alphabetically.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSortFilterOptions() {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    // Get the exposed form render array.
    $output = $this->getExposedFormRenderArray($view);

    // Assert our "field_bef_integer" filter options are not sorted
    // alphabetically, but by key.
    $sorted_options = $options = $output['field_bef_integer_value']['#options'];
    asort($sorted_options);

    $this->assertNotEquals(array_keys($options), array_keys($sorted_options), '"Field BEF integer" options are not sorted alphabetically.');

    $view->destroy();

    // Enable sort for filter options.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'sort_options' => TRUE,
          ],
        ],
      ],
    ]);

    // Get the exposed form render array.
    $output = $this->getExposedFormRenderArray($view);

    // Assert our "field_bef_integer" filter options are sorted alphabetically.
    $sorted_options = $options = $output['field_bef_integer_value']['#options'];
    asort($sorted_options);

    // Assert our "collapsible" options detail is visible.
    $this->assertEquals(array_keys($options), array_keys($sorted_options));

    $view->destroy();
  }

  /**
   * Tests moving filter option into collapsible fieldset.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCollapsibleOption() {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    // Enable collapsible options.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_email_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'collapsible' => TRUE,
          ],
        ],
      ],
    ]);

    // Render the exposed form.
    $this->renderExposedForm($view);

    // Assert our "collapsible" options detail is visible.
    $actual = $this->xpath("//form//details[@data-drupal-selector='edit-field-bef-email-value-collapsible']");
    $this->assertCount(1, $actual);

    $view->destroy();
  }

  /**
   * Tests that a non-"- Any -" first option is sorted correctly.
   *
   * Checks if the first non-"- Any -" option is sorted correctly when
   * alphabetical sorting is enabled, especially when "Allow multiple
   * selections" is active (which removes "- Any -").
   *
   * This test uses BEF's option rewriting on an existing filter to simulate
   * an initial non-alphabetical order.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFirstNonAnyOptionIsSortedWithMultipleSelections() {
    $view_id = 'bef_test';
    $display_id = 'default';
    $filter_id = 'field_bef_letters_value';

    $view = Views::getView($view_id);
    $display_storage = &$view->storage->getDisplay($display_id);

    // Configure the Views Filter for the bug scenario.
    $this->assertArrayHasKey($filter_id, $display_storage['display_options']['filters'], "Filter '$filter_id' not found in view storage. Check bef_test.yml.");
    $display_storage['display_options']['filters'][$filter_id]['expose']['multiple'] = TRUE;
    $display_storage['display_options']['filters'][$filter_id]['expose']['required'] = FALSE;

    // Save the view configuration changes.
    $view->save();
    // Reload the view after saving storage changes.
    $view = Views::getView($view_id);

    // BEF settings for the filter.
    $rewrite_rules = "a|Mango\nb|Apple\nc|Cherry";

    $this->setBetterExposedOptions($view, [
      'filter' => [
        $filter_id => [
          'plugin_id' => 'default',
          'advanced' => [
            'rewrite' => [
              'filter_rewrite_values' => $rewrite_rules,
              'filter_rewrite_values_key' => TRUE,
            ],
            'sort_options' => TRUE,
          ],
        ],
      ],
    ]);

    // Re-initialize the view to apply BEF settings.
    $view = Views::getView($view_id);
    $output = $this->getExposedFormRenderArray($view);

    $this->assertArrayHasKey($filter_id, $output, "Filter '$filter_id' not found in the rendered exposed form.");
    if (!isset($output[$filter_id]['#options'])) {
      $this->fail("Filter '$filter_id' does not have #options in the output. Check field data, rewrite rules, and filter exposure.");
    }
    $options_from_form = $output[$filter_id]['#options'];

    // Expected sorted order of these five labels.
    $expected_labels_order = [
      'Apple',
      'Cherry',
      'Donkey',
      'Elephant',
      'Mango',
    ];
    // Get actual labels, ensuring TranslatableMarkup objects are cast to
    // strings.
    $actual_labels_order = array_map(function ($option_label) {
      return $option_label;
    }, array_values($options_from_form));

    $this->assertEquals($expected_labels_order, $actual_labels_order);

    $view->destroy();
  }

  /**
   * Tests that a rewritten "- Any -" option is correctly preserved at the top.
   *
   * When alphabetical sorting is enabled, even if "Allow multiple selections"
   * is true.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRewrittenAnyOptionIsPreservedWithSorting() {
    $view_id = 'bef_test';
    $display_id = 'default';
    $filter_id = 'field_bef_letters_value';

    $view = Views::getView($view_id);
    $display_storage = &$view->storage->getDisplay($display_id);

    $this->assertArrayHasKey($filter_id, $display_storage['display_options']['filters'], "Filter '$filter_id' not found in view storage.");
    $display_storage['display_options']['filters'][$filter_id]['expose']['multiple'] = TRUE;
    $display_storage['display_options']['filters'][$filter_id]['expose']['required'] = FALSE;
    $view->save();
    $view = Views::getView($view_id);

    // Rewrite key 'a' to "- Any -", 'b' to "Orange", 'c' to "Banana".
    // Options for original keys 'd' ('Donkey') and 'e' ('Elephant')
    // will pass through.
    $rewrite_rules = "a|- Any -\nb|Orange\nc|Banana";

    $this->setBetterExposedOptions($view, [
      'filter' => [
        $filter_id => [
          'plugin_id' => 'default',
          'advanced' => [
            'rewrite' => [
              'filter_rewrite_values' => $rewrite_rules,
              'filter_rewrite_values_key' => TRUE,
            ],
            'sort_options' => TRUE,
          ],
        ],
      ],
    ]);

    $output = $this->getExposedFormRenderArray($view, $display_id);

    $this->assertArrayHasKey($filter_id, $output, "Filter '$filter_id' not found in the rendered form.");
    if (!isset($output[$filter_id]['#options'])) {
      $this->fail("Filter '$filter_id' does not have #options. Check configuration and rewrite rules.");
    }
    $options_from_form = $output[$filter_id]['#options'];

    $this->assertCount(5, $options_from_form, "Should be 5 options: 3 rewritten ('- Any -', 'Orange', 'Banana') and 2 passed through ('Donkey', 'Elephant').");

    // Check that the option originally keyed 'a' (now "- Any -") is present and
    // is the first key.
    $this->assertArrayHasKey('a', $options_from_form, "Option with original key 'a' (rewritten to '- Any -') should be present in the final options set.");
    $first_key_in_final_options = array_key_first($options_from_form);
    $this->assertEquals('a', $first_key_in_final_options, "The option originally keyed 'a' (rewritten to '- Any -') should be the first item in the sorted list due to preservation.");

    // Assert the label of the first option (key 'a') is the string "- Any -".
    $this->assertEquals('- Any -', $options_from_form['a'], "The label of the first option (key 'a') should be '- Any -'.");

    // Check that the remaining options are sorted alphabetically.
    $remaining_options_with_keys = $options_from_form;
    unset($remaining_options_with_keys[$first_key_in_final_options]);

    $actual_remaining_labels = array_map(function ($label_obj) {
      if ($label_obj instanceof TranslatableMarkup) {
        return $label_obj->__toString();
      }
      return (string) $label_obj;
    }, array_values($remaining_options_with_keys));

    $expected_remaining_labels_sorted = [
      'Banana',
      'Donkey',
      'Elephant',
      'Orange',
    ];

    $this->assertEquals($expected_remaining_labels_sorted, $actual_remaining_labels);

    $view->destroy();
  }

}

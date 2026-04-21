<?php

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\sort;

use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the advanced options of a sort widget.
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort\SortWidgetBase
 */
class SortWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests combining sort options.
   *
   * Tests combining sort order and sort by, as well as using a custom
   * query string parameter.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCombineSortOptions() {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    // Ensure our sort "created" is exposed.
    $display['display_options']['sorts']['created']['exposed'] = TRUE;
    $display['display_options']['sorts']['created']['expose']['label'] = 'Created';

    // Enable combined sort.
    $this->setBetterExposedOptions($view, [
      'sort' => [
        'advanced' => [
          'combine' => TRUE,
          'combine_param' => 'sort_bef_combine_test',
        ],
      ],
    ]);

    // Get the exposed form render array.
    $output = $this->getExposedFormRenderArray($view);

    // Assert our "sort_bef_combine" contains both sort by and sort order
    // options.
    $options = $output['sort_bef_combine_test']['#options'];
    $assert = [
      'created_ASC' => 'Created Asc',
      'created_DESC' => 'Created Desc',
    ];

    // Assert our combined sort options are added.
    $this->assertEquals($options, $assert);

    $view->destroy();
  }

  /**
   * Tests combining and rewriting sort options (sort order and sort by).
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCombineRewriteSortOptions() {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    // Ensure our sort "created" is exposed.
    $display['display_options']['sorts']['created']['exposed'] = TRUE;
    $display['display_options']['sorts']['created']['expose']['label'] = 'Created';

    // Enable combined sort and rewrite options.
    $this->setBetterExposedOptions($view, [
      'sort' => [
        'advanced' => [
          'combine' => TRUE,
          'combine_param' => 'sort_bef_combine_test',
          'combine_rewrite' => "Created Desc|down\r\nCreated Asc|up",
        ],
      ],
    ]);

    // Get the exposed form render array.
    $output = $this->getExposedFormRenderArray($view);

    // Assert our "sort_bef_combine" contains both sort by and sort order
    // options, and has its options rewritten.
    $options = $output['sort_bef_combine_test']['#options'];
    $assert = [
      'created_DESC' => 'down',
      'created_ASC' => 'up',
    ];

    // Assert our combined sort options are added.
    $this->assertEquals($options, $assert);

    $view->destroy();
  }

  /**
   * Tests adding a reset sort option.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testResetSortOptions() {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');

    // Ensure our sort "created" is exposed.
    $display['display_options']['sorts']['created']['exposed'] = TRUE;
    $display['display_options']['sorts']['created']['expose']['label'] = 'Created';

    // Enable combined sort and rewrite options.
    $this->setBetterExposedOptions($view, [
      'sort' => [
        'advanced' => [
          'combine' => TRUE,
          'combine_param' => 'sort_bef_combine_test',
          'reset' => TRUE,
          'reset_label' => 'Reset sort',
        ],
      ],
    ]);

    // Get the exposed form render array.
    $output = $this->getExposedFormRenderArray($view);

    // Assert our "sort_bef_combine" contains a reset option at the top.
    $options = $output['sort_bef_combine_test']['#options'];
    $assert = [
      ' ' => 'Reset sort',
      'created_ASC' => 'Created Asc',
      'created_DESC' => 'Created Desc',
    ];

    // Assert our combined sort options are added.
    $this->assertEquals($options, $assert);

    $view->destroy();
  }

}

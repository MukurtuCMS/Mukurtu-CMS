<?php

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\sort;

use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the radio buttons sort widget (i.e. "bef").
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort\RadioButtons
 */
class RadioButtonsSortWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests the exposed radio buttons sort widget.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedRadioButtons() {
    $view = Views::getView('bef_test');

    // Change exposed sort to radio buttons (i.e. 'bef').
    $this->setBetterExposedOptions($view, [
      'sort' => [
        'plugin_id' => 'bef',
      ],
    ]);

    // Render the exposed form.
    $this->renderExposedForm($view);

    // Check our sort item "sort_by" is rendered as links.
    $actual = $this->xpath('//form//input[@type="radio" and starts-with(@id, "edit-sort-by")]');
    $this->assertCount(1, $actual);

    // Check our sort item "sort_order" is rendered as links.
    $actual = $this->xpath('//form//input[@type="radio" and starts-with(@id, "edit-sort-order")]');
    $this->assertCount(2, $actual);

    $view->destroy();
  }

}

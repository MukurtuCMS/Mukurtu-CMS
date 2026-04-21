<?php

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\sort;

use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the links sort widget (i.e. "bef_links").
 *
 * @group better_exposed_filters
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort\Links
 */
class LinksSortWidgetKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Tests the exposed links sort widget.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedLinks() {
    $view = Views::getView('bef_test');

    // Change exposed sort to links (i.e. 'bef_links').
    $this->setBetterExposedOptions($view, [
      'sort' => [
        'plugin_id' => 'bef_links',
      ],
    ]);

    // Render the exposed form.
    $this->renderExposedForm($view);

    // Check our sort item "sort_by" is rendered as links.
    $actual = $this->xpath('//form//a[starts-with(@id, "edit-sort-by")]');
    $this->assertCount(1, $actual);

    // Check our sort item "sort_order" is rendered as links.
    $actual = $this->xpath('//form//a[starts-with(@id, "edit-sort-order")]');
    $this->assertCount(2, $actual);

    $view->destroy();
  }

}

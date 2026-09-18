<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The shipped mukurtu_categories view actually hides admin links.
 *
 * ShippedDisplayConfigTest (tests/src/Unit) pins the raw show_admin_links
 * value in each display's own config, but that value is not sufficient on
 * its own to prove anything: DisplayPluginBase::isDefaulted() makes a
 * display inherit the master display's value unless its own 'defaults'
 * array explicitly opts out, which none of these displays does. This test
 * installs the actual shipped config on a fresh site and resolves each
 * display's option the way \Drupal\views\Views::getView() does, which is
 * the only way to catch a display whose own value is real but inert.
 *
 * @see mukurtu_core_update_40204()
 */
#[Group('mukurtu_core')]
class CategoryAdminLinksShippedResolutionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'taxonomy', 'field', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core')
      . '/config/install/views.view.mukurtu_categories.yml';
    $values = Yaml::decode(file_get_contents($config_path));
    unset($values['dependencies'], $values['_core']);
    View::create($values)->save();
  }

  /**
   * Every shipped display resolves show_admin_links to FALSE.
   */
  #[DataProvider('displayProvider')]
  public function testShippedDisplayHidesAdminLinks(string $display_id): void {
    $view = Views::getView('mukurtu_categories');
    $view->setDisplay($display_id);

    $this->assertFalse(
      $view->display_handler->getOption('show_admin_links'),
      "The $display_id display would render contextual admin links, even though its shipped config says otherwise."
    );
  }

  public static function displayProvider(): \Generator {
    yield 'master' => ['default'];
    yield 'homepage block' => ['browse_by_category_block'];
    yield 'categories page' => ['categories_page'];
  }

}

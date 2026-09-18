<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40204(), which hides admin links on the view.
 *
 * Asserts through \Drupal\views\Views::getView() and the display plugin's own
 * getOption(), not the raw config array. A display without a 'defaults'
 * override for 'show_admin_links' always inherits the master display's
 * value regardless of what its own display_options says, so a test that
 * only inspects the saved array (as an earlier version of this test did)
 * would pass while the real page still rendered the contextual link.
 *
 * @see mukurtu_core_update_40204()
 */
#[Group('mukurtu_core')]
class CategoryAdminLinksCascadeUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required directly rather than via loadInclude(). mukurtu_core is not
    // enabled here - its dependency chain is far heavier than this hook needs -
    // and loadInclude() can only resolve a module the kernel knows about, so it
    // silently does nothing for a profile-nested module that is switched off.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * Creates a minimal mukurtu_categories view, master display unset.
   *
   * The child displays each carry their own (provably ineffective without a
   * matching 'defaults' override) show_admin_links: false, matching what
   * mukurtu_core_update_40094() left behind in production before it was
   * removed in the 4.0.1 hook strip.
   */
  private function makeView(): View {
    $view = View::create([
      'id' => 'mukurtu_categories',
      'label' => 'Categories',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [],
        ],
        'browse_by_category_block' => [
          'display_plugin' => 'block',
          'id' => 'browse_by_category_block',
          'display_options' => [
            'show_admin_links' => FALSE,
          ],
        ],
        'categories_page' => [
          'display_plugin' => 'page',
          'id' => 'categories_page',
          'display_options' => [
            'show_admin_links' => FALSE,
          ],
        ],
      ],
    ]);
    $view->save();

    return $view;
  }

  /**
   * Every display resolves show_admin_links to FALSE after the update.
   */
  public function testHidesAdminLinksAcrossAllDisplays(): void {
    $this->makeView();

    // Precondition: despite each child display's own FALSE value, the real
    // Views resolution still reports TRUE before the fix, because both
    // inherit the master display's unset (and therefore plugin-default TRUE)
    // value. If this ever stops being true, the bug this update hook fixes
    // no longer reproduces the way its docblock describes.
    $view = Views::getView('mukurtu_categories');
    foreach (['default', 'browse_by_category_block', 'categories_page'] as $display_id) {
      $view->setDisplay($display_id);
      $this->assertTrue(
        $view->display_handler->getOption('show_admin_links'),
        "Precondition: $display_id no longer inherits the master display's TRUE value; this test would pass vacuously."
      );
    }

    mukurtu_core_update_40204();

    $view = Views::getView('mukurtu_categories');
    foreach (['default', 'browse_by_category_block', 'categories_page'] as $display_id) {
      $view->setDisplay($display_id);
      $this->assertFalse(
        $view->display_handler->getOption('show_admin_links'),
        "The $display_id display would still render contextual admin links."
      );
    }
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeView();

    mukurtu_core_update_40204();
    mukurtu_core_update_40204();

    $view = View::load('mukurtu_categories');
    $this->assertFalse(
      $view->get('display')['default']['display_options']['show_admin_links'] ?? NULL
    );
  }

  /**
   * A site with no mukurtu_categories view at all does not error.
   */
  public function testMissingViewDoesNotError(): void {
    $this->assertNull(View::load('mukurtu_categories'));
    mukurtu_core_update_40204();
    $this->assertNull(View::load('mukurtu_categories'));
  }

}

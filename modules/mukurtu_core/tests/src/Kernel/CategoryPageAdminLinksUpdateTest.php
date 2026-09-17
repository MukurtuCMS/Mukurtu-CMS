<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40204(), which hides admin links on /categories.
 *
 * @see mukurtu_core_update_40204()
 */
#[Group('mukurtu_core')]
class CategoryPageAdminLinksUpdateTest extends KernelTestBase {

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
   * Creates a minimal mukurtu_categories view with the given display options.
   */
  private function makeView(bool $blockShowsAdminLinks, ?bool $pageShowsAdminLinks): View {
    $display = [
      'display_options' => [],
    ];
    if ($pageShowsAdminLinks !== NULL) {
      $display['display_options']['show_admin_links'] = $pageShowsAdminLinks;
    }

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
            'show_admin_links' => $blockShowsAdminLinks,
          ],
        ],
        'categories_page' => $display + ['display_plugin' => 'page', 'id' => 'categories_page'],
      ],
    ]);
    $view->save();

    return $view;
  }

  /**
   * The categories_page display has admin links turned off.
   */
  public function testHidesAdminLinksOnCategoriesPage(): void {
    $this->makeView(blockShowsAdminLinks: FALSE, pageShowsAdminLinks: NULL);

    mukurtu_core_update_40204();

    $view = View::load('mukurtu_categories');
    $this->assertFalse(
      $view->get('display')['categories_page']['display_options']['show_admin_links'] ?? NULL,
      'The categories page would still render contextual admin links.'
    );
  }

  /**
   * The browse_by_category_block display, already fixed by #1919, is untouched.
   */
  public function testLeavesBlockDisplayAlone(): void {
    $this->makeView(blockShowsAdminLinks: FALSE, pageShowsAdminLinks: NULL);

    mukurtu_core_update_40204();

    $view = View::load('mukurtu_categories');
    $this->assertFalse(
      $view->get('display')['browse_by_category_block']['display_options']['show_admin_links'] ?? NULL,
      'The already-fixed block display was changed unexpectedly.'
    );
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeView(blockShowsAdminLinks: FALSE, pageShowsAdminLinks: NULL);

    mukurtu_core_update_40204();
    mukurtu_core_update_40204();

    $view = View::load('mukurtu_categories');
    $this->assertFalse(
      $view->get('display')['categories_page']['display_options']['show_admin_links'] ?? NULL
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

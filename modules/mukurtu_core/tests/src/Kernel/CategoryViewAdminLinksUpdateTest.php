<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40094(), which disables the Browse by Category
 * block's contextual/admin links.
 *
 * @see mukurtu_core_update_40094()
 */
#[Group('mukurtu_core')]
class CategoryViewAdminLinksUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The hook writes a partial views config fixture, not full schema data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * The update hook sets show_admin_links to FALSE on the block display.
   */
  public function testUpdateDisablesShowAdminLinksOnBlockDisplay(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_categories')
      ->setData([
        'id' => 'mukurtu_categories',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'title' => 'Categories',
            ],
          ],
          'browse_by_category_block' => [
            'id' => 'browse_by_category_block',
            'display_options' => [
              'title' => 'Browse by Category',
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40094();

    $config = \Drupal::config('views.view.mukurtu_categories');
    $this->assertFalse($config->get('display.browse_by_category_block.display_options.show_admin_links'));
  }

  /**
   * The update hook doesn't touch other displays on the same view.
   */
  public function testUpdateLeavesOtherDisplaysUntouched(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_categories')
      ->setData([
        'id' => 'mukurtu_categories',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'title' => 'Categories',
            ],
          ],
          'browse_by_category_block' => [
            'id' => 'browse_by_category_block',
            'display_options' => [
              'title' => 'Browse by Category',
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40094();

    $config = \Drupal::config('views.view.mukurtu_categories');
    $this->assertNull($config->get('display.default.display_options.show_admin_links'));
    $this->assertEquals('Categories', $config->get('display.default.display_options.title'));
  }

  /**
   * The update hook is a no-op when the view doesn't exist on the site.
   */
  public function testUpdateIsNoOpWithoutView(): void {
    $this->assertTrue(\Drupal::config('views.view.mukurtu_categories')->isNew());
    mukurtu_core_update_40094();
    $this->assertTrue(\Drupal::config('views.view.mukurtu_categories')->isNew());
  }

  /**
   * The update hook is a no-op when the block display has been removed.
   */
  public function testUpdateIsNoOpWithoutBlockDisplay(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_categories')
      ->setData([
        'id' => 'mukurtu_categories',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'title' => 'Categories',
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40094();

    $config = \Drupal::config('views.view.mukurtu_categories');
    $this->assertNull($config->get('display.browse_by_category_block'));
  }

}

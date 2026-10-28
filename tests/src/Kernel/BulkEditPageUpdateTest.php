<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40036().
 *
 * Existing sites get the bulk edit page's view from the shipped config.
 */
#[Group('mukurtu')]
class BulkEditPageUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The view names filter plugins from many Mukurtu modules. The hook only
   * imports the shipped config, so this checks the import, not the plugins.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'system', 'user', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * The view is imported, and a second run leaves it alone.
   */
  public function testInstallsViewOnce(): void {
    $this->assertNull(View::load('mukurtu_bulk_edit_content'));

    $this->assertStringContainsString('Added', mukurtu_update_40036());

    $view = View::load('mukurtu_bulk_edit_content');
    $this->assertNotNull($view);
    $this->assertSame('admin/content/bulk/%', $view->getDisplay('page')['display_options']['path']);
    $this->assertSame('use mukurtu bulk edit', $view->getDisplay('default')['display_options']['access']['options']['perm']);

    $view->set('label', 'Site label')->save();
    $this->assertStringContainsString('nothing to change', mukurtu_update_40036());
    $this->assertSame('Site label', View::load('mukurtu_bulk_edit_content')->label());
  }

}

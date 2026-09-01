<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40029().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_update_40029()
 */
#[Group('mukurtu_core')]
class CategoriesViewLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'taxonomy', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.profile')->getPath('mukurtu') . '/config/install');

    $data_mukurtu_categories = $source->read('views.view.mukurtu_categories');
    unset($data_mukurtu_categories['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_categories['display']['browse_by_category_block']['display_options']['rendering_language']);
    unset($data_mukurtu_categories['display']['categories_page']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_categories')->setData($data_mukurtu_categories)->save();

    require_once \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_categories')->get('display.default.display_options.filters') ?? []);

    mukurtu_update_40029();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_categories')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_categories')->get('display.browse_by_category_block.display_options.rendering_language'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_categories')->get('display.categories_page.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_update_40029();
    mukurtu_update_40029();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_categories')->get('display.default.display_options.filters'));
  }

}

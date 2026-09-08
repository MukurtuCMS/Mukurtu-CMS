<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_multipage_items_update_40011().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_multipage_items_update_40011()
 */
#[Group('mukurtu_multipage_items')]
class MultipageItemBrowserViewLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'entity_browser', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_multipage_items') . '/config/install');

    $data_multipage_item_browser = $source->read('views.view.multipage_item_browser');
    unset($data_multipage_item_browser['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_multipage_item_browser['display']['entity_browser']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.multipage_item_browser')->setData($data_multipage_item_browser)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_multipage_items') . '/mukurtu_multipage_items.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.multipage_item_browser')->get('display.default.display_options.filters') ?? []);

    mukurtu_multipage_items_update_40011();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.multipage_item_browser')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.multipage_item_browser')->get('display.entity_browser.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_multipage_items_update_40011();
    mukurtu_multipage_items_update_40011();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.multipage_item_browser')->get('display.default.display_options.filters'));
  }

}

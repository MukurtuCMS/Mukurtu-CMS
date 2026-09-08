<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40106().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_core_update_40106()
 */
#[Group('mukurtu_core')]
class ContentBrowserViewLanguageFallbackTest extends KernelTestBase {

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
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_core') . '/config/install');

    $data_mukurtu_content_browser = $source->read('views.view.mukurtu_content_browser');
    unset($data_mukurtu_content_browser['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_content_browser['display']['entity_browser']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_content_browser')->setData($data_mukurtu_content_browser)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_core') . '/mukurtu_core.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_content_browser')->get('display.default.display_options.filters') ?? []);

    mukurtu_core_update_40106();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_content_browser')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_content_browser')->get('display.entity_browser.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_core_update_40106();
    mukurtu_core_update_40106();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_content_browser')->get('display.default.display_options.filters'));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_protocol_update_40042().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_protocol_update_40042()
 * @group mukurtu_protocol
 */
class CommunityViewsLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'mukurtu_protocol', 'entity_browser', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_protocol') . '/config/install');

    $data_browse_by_community = $source->read('views.view.browse_by_community');
    unset($data_browse_by_community['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_browse_by_community['display']['community_browse_block']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.browse_by_community')->setData($data_browse_by_community)->save();

    $data_mukurtu_community_select = $source->read('views.view.mukurtu_community_select');
    unset($data_mukurtu_community_select['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_community_select['display']['mukurtu_community_select']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_community_select')->setData($data_mukurtu_community_select)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_protocol') . '/mukurtu_protocol.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.browse_by_community')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_community_select')->get('display.default.display_options.filters') ?? []);

    mukurtu_protocol_update_40042();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.browse_by_community')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.browse_by_community')->get('display.community_browse_block.display_options.rendering_language'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_community_select')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_community_select')->get('display.mukurtu_community_select.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_protocol_update_40042();
    mukurtu_protocol_update_40042();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.browse_by_community')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_community_select')->get('display.default.display_options.filters'));
  }

}

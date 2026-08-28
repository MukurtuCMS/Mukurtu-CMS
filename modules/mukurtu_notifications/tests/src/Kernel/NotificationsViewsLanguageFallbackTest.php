<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_notifications_update_40056().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_notifications_update_40056()
 * @group mukurtu_notifications
 */
class NotificationsViewsLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'message', 'filter', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_notifications') . '/config/install');

    $data_mukurtu_recent_content = $source->read('views.view.mukurtu_recent_content');
    unset($data_mukurtu_recent_content['display']['default']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_recent_content['display']['all_recent_content_block']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_recent_content')->setData($data_mukurtu_recent_content)->save();

    $data_mukurtu_message_log = $source->read('views.view.mukurtu_message_log');
    unset($data_mukurtu_message_log['display']['mukurtu_notifications_page']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_message_log['display']['mukurtu_notifications_page']['display_options']['rendering_language']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_message_log')->setData($data_mukurtu_message_log)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_notifications') . '/mukurtu_notifications.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_recent_content')->get('display.default.display_options.filters') ?? []);
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_message_log')->get('display.mukurtu_notifications_page.display_options.filters') ?? []);

    mukurtu_notifications_update_40056();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_recent_content')->get('display.default.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_recent_content')->get('display.all_recent_content_block.display_options.rendering_language'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_message_log')->get('display.mukurtu_notifications_page.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_message_log')->get('display.mukurtu_notifications_page.display_options.rendering_language'));

    // The admin display is intentionally untouched - only /notifications is in policy scope.
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_message_log')->get('display.mukurtu_notifications_admin_page.display_options.filters') ?? []);
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_notifications_update_40056();
    mukurtu_notifications_update_40056();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_recent_content')->get('display.default.display_options.filters'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_message_log')->get('display.mukurtu_notifications_page.display_options.filters'));
  }

}

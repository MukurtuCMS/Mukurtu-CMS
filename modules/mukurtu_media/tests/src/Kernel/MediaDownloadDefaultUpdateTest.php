<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_media_update_40023(), which defaults the media download
 * button to off (#1761).
 *
 * @see mukurtu_media_update_40023()
 */
#[Group('mukurtu_media')]
class MediaDownloadDefaultUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_media');
    require_once $module_path . '/mukurtu_media.install';
  }

  /**
   * The update hook turns the shipped-enabled default off.
   */
  public function testUpdateDisablesDownloadByDefault(): void {
    \Drupal::configFactory()->getEditable('mukurtu_media.settings')
      ->set('mukurtu_media_download_enabled', TRUE)
      ->save();

    mukurtu_media_update_40023();

    $this->assertFalse(\Drupal::config('mukurtu_media.settings')->get('mukurtu_media_download_enabled'));
  }

  /**
   * The update hook leaves a site that already disabled it alone (no-op).
   */
  public function testUpdateLeavesAlreadyDisabledAlone(): void {
    \Drupal::configFactory()->getEditable('mukurtu_media.settings')
      ->set('mukurtu_media_download_enabled', FALSE)
      ->save();

    mukurtu_media_update_40023();

    $this->assertFalse(\Drupal::config('mukurtu_media.settings')->get('mukurtu_media_download_enabled'));
  }

  /**
   * The update hook is a no-op without config (module not yet installed).
   */
  public function testUpdateIsNoOpWithoutConfig(): void {
    $this->assertTrue(\Drupal::config('mukurtu_media.settings')->isNew());
    mukurtu_media_update_40023();
    $this->assertTrue(\Drupal::config('mukurtu_media.settings')->isNew());
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_gin_custom_update_10000(), which registers gin_lb's
 * Toastify CDN script as a required, opt-out Klaro service so Klaro's
 * "Block unknown external resources" setting doesn't rewrite it into a
 * consent-gated placeholder - see the update hook's own docblock for how
 * that blocking breaks Layout Builder entirely.
 *
 * @see mukurtu_gin_custom_update_10000()
 */
#[Group('mukurtu_gin_custom')]
class ToastifyKlaroServiceUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'klaro',
    'mukurtu_gin_custom',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_gin_custom');
    require_once $module_path . '/mukurtu_gin_custom.install';
  }

  /**
   * The update hook creates a required, opt-out Toastify service.
   */
  public function testUpdateCreatesRequiredOptOutService(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $this->assertNull($storage->load('toastify'));

    mukurtu_gin_custom_update_10000();

    $app = $storage->load('toastify');
    $this->assertNotNull($app);
    $this->assertTrue($app->get('required'));
    $this->assertTrue($app->get('opt_out'));
    $this->assertTrue($app->get('status'));
    $this->assertContains('cdn.jsdelivr.net/npm/toastify-js', $app->get('javascripts'));
  }

  /**
   * The update hook doesn't error or duplicate if the service already exists.
   */
  public function testUpdateIsIdempotent(): void {
    mukurtu_gin_custom_update_10000();
    mukurtu_gin_custom_update_10000();

    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $this->assertNotNull($storage->load('toastify'));
    $this->assertCount(1, $storage->loadByProperties(['id' => 'toastify']));
  }

}

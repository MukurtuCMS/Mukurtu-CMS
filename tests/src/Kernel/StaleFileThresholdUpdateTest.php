<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40031().
 *
 * The profile shipped system.performance:stale_file_threshold after Drupal
 * 11.4 core dropped it from the schema, so installing the profile under strict
 * schema checking failed. The hook clears the key from existing sites.
 */
#[Group('mukurtu')]
class StaleFileThresholdUpdateTest extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Reads the profile's shipped system.performance config.
   */
  protected function shippedConfig(): array {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read('system.performance');
    $this->assertIsArray($data);
    return $data;
  }

  /**
   * Writes system.performance as a 4.0.x install left it.
   */
  protected function writeLegacyConfig(): void {
    $data = $this->shippedConfig();
    $data['stale_file_threshold'] = 2592000;
    // Bypass the schema-checking config factory, as a real site's stored
    // config would.
    \Drupal::service('config.storage')->write('system.performance', $data);
    \Drupal::configFactory()->reset('system.performance');
  }

  /**
   * The obsolete key is removed and the other settings are kept.
   */
  public function testRemovesTheKey(): void {
    $this->writeLegacyConfig();

    $message = mukurtu_update_40031();

    $data = \Drupal::config('system.performance')->getRawData();
    $this->assertArrayNotHasKey('stale_file_threshold', $data);
    $this->assertSame(0, $data['cache']['page']['max_age']);
    $this->assertTrue($data['css']['preprocess']);
    $this->assertStringContainsString('Removed', $message);
  }

  /**
   * Running twice is a no-op the second time.
   */
  public function testIsIdempotent(): void {
    $this->writeLegacyConfig();

    mukurtu_update_40031();
    $message = mukurtu_update_40031();

    $this->assertArrayNotHasKey('stale_file_threshold', \Drupal::config('system.performance')->getRawData());
    $this->assertStringContainsString('nothing to change', $message);
  }

  /**
   * The shipped config matches core's schema, so a fresh install is clean.
   */
  public function testShippedConfigMatchesSchema(): void {
    $data = $this->shippedConfig();

    $this->assertArrayNotHasKey('stale_file_threshold', $data);
    $this->assertTrue($this->checkConfigSchema(\Drupal::service('config.typed'), 'system.performance', $data));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_design_update_40005().
 */
#[Group('mukurtu_design')]
class PageBackgroundSettingsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mukurtu_design']);
    require_once $this->root . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_design') . '/mukurtu_design.install';
  }

  /**
   * Removes the background settings, as a site upgrading from 4.0.1 would have.
   */
  protected function writePreUpdateState(): void {
    $this->config('mukurtu_design.settings')->clear('background')->save();
  }

  /**
   * Returns the stored background settings.
   */
  protected function background() {
    return \Drupal::config('mukurtu_design.settings')->get('background');
  }

  /**
   * A 4.0.1 site gets the settings, switched off.
   */
  public function testSeedsTheSettings(): void {
    $this->writePreUpdateState();
    $this->assertNull($this->background());

    mukurtu_design_update_40005();

    $this->assertSame(
      ['image' => NULL, 'text_treatment' => 'light'],
      $this->background()
    );
  }

  /**
   * Running twice reports no work and changes nothing.
   */
  public function testIsIdempotent(): void {
    $this->writePreUpdateState();

    mukurtu_design_update_40005();
    $after_first = $this->background();

    $message = mukurtu_design_update_40005();

    $this->assertSame($after_first, $this->background());
    $this->assertStringContainsString('already present', $message);
  }

  /**
   * A configured background survives the update untouched.
   *
   * The shipped default for image is NULL, so a naive isset()/get() check
   * would treat a configured site as unconfigured and reset it.
   */
  public function testDoesNotResetConfiguredBackground(): void {
    $this->config('mukurtu_design.settings')
      ->set('background', ['image' => 7, 'text_treatment' => 'dark'])
      ->save();

    mukurtu_design_update_40005();

    $this->assertSame(
      ['image' => 7, 'text_treatment' => 'dark'],
      $this->background()
    );
  }

  /**
   * A partially seeded site is completed rather than overwritten.
   */
  public function testCompletesPartialBackground(): void {
    $this->config('mukurtu_design.settings')->set('background', ['text_treatment' => 'dark'])->save();

    mukurtu_design_update_40005();

    $background = $this->background();
    $this->assertSame('dark', $background['text_treatment'], 'The existing choice is kept.');
    $this->assertArrayHasKey('image', $background);
  }

  /**
   * A fresh install must match what the update hook produces.
   */
  public function testFreshInstallMatchesTheHook(): void {
    $fresh = $this->background();

    $this->writePreUpdateState();
    mukurtu_design_update_40005();

    $this->assertSame($fresh, $this->background());
  }

}

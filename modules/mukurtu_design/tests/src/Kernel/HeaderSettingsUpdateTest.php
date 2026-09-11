<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_design_update_40005().
 */
#[Group('mukurtu_design')]
class HeaderSettingsUpdateTest extends KernelTestBase {

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
   * Removes the header settings, as a site upgrading from 4.0.1 would have.
   */
  protected function writePreUpdateState(): void {
    $this->config('mukurtu_design.settings')->clear('header')->save();
  }

  /**
   * Returns the stored header settings.
   */
  protected function header() {
    return \Drupal::config('mukurtu_design.settings')->get('header');
  }

  /**
   * A 4.0.1 site gets the settings, switched off.
   */
  public function testSeedsTheSettings(): void {
    $this->writePreUpdateState();
    $this->assertNull($this->header());

    mukurtu_design_update_40005();

    $this->assertSame(
      ['image' => NULL, 'show_on_front' => TRUE, 'text_treatment' => 'light'],
      $this->header()
    );
  }

  /**
   * Running twice reports no work and changes nothing.
   */
  public function testIsIdempotent(): void {
    $this->writePreUpdateState();

    mukurtu_design_update_40005();
    $after_first = $this->header();

    $message = mukurtu_design_update_40005();

    $this->assertSame($after_first, $this->header());
    $this->assertStringContainsString('already present', $message);
  }

  /**
   * A configured header survives the update untouched.
   *
   * The shipped default for image is NULL, so a naive isset()/get() check
   * would treat a configured site as unconfigured and reset it.
   */
  public function testDoesNotResetConfiguredHeader(): void {
    $this->config('mukurtu_design.settings')
      ->set('header', ['image' => 7, 'show_on_front' => FALSE, 'text_treatment' => 'dark'])
      ->save();

    mukurtu_design_update_40005();

    $this->assertSame(
      ['image' => 7, 'show_on_front' => FALSE, 'text_treatment' => 'dark'],
      $this->header()
    );
  }

  /**
   * A partially seeded site is completed rather than overwritten.
   */
  public function testCompletesPartialHeader(): void {
    $this->config('mukurtu_design.settings')->set('header', ['text_treatment' => 'dark'])->save();

    mukurtu_design_update_40005();

    $header = $this->header();
    $this->assertSame('dark', $header['text_treatment'], 'The existing choice is kept.');
    $this->assertArrayHasKey('image', $header);
    $this->assertTrue($header['show_on_front']);
  }

  /**
   * A fresh install must match what the update hook produces.
   */
  public function testFreshInstallMatchesTheHook(): void {
    $fresh = $this->header();

    $this->writePreUpdateState();
    mukurtu_design_update_40005();

    $this->assertSame($fresh, $this->header());
  }

}

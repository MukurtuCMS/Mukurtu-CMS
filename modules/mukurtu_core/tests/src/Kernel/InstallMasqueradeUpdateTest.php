<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40209().
 *
 * @see mukurtu_core_update_40209()
 */
#[Group('mukurtu_core')]
class InstallMasqueradeUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required directly rather than via loadInclude(), for the same reason as
    // RevokeAuthenticatedFullHtmlUpdateTest: mukurtu_core is not enabled here,
    // and loadInclude() cannot resolve a profile-nested module that is
    // switched off.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * A site without masquerade gets it installed, and is told so.
   */
  public function testInstallsMasquerade(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('masquerade'), 'Precondition: masquerade is already enabled, so installing it here would prove nothing.');

    $message = mukurtu_core_update_40209();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('masquerade'), 'masquerade was not installed.');
    $this->assertNotNull($message, 'The operator was told nothing about the module being installed.');
    $this->assertStringContainsString('Masquerade', (string) $message);
  }

  /**
   * A site that already has masquerade is left alone, and says nothing.
   */
  public function testNoOpWhenAlreadyInstalled(): void {
    \Drupal::service('module_installer')->install(['masquerade']);

    $this->assertNull(mukurtu_core_update_40209());
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('masquerade'));
  }

}

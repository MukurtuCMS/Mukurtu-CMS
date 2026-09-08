<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_bot_protection\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_bot_protection_update_40001(), which raises the facet
 * crawler-block limit from 2 to 4 (#1761).
 *
 * @see mukurtu_bot_protection_update_40001()
 */
#[Group('mukurtu_bot_protection')]
class FacetBotBlockerLimitUpdateTest extends KernelTestBase {

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
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_bot_protection');
    require_once $module_path . '/mukurtu_bot_protection.install';
  }

  /**
   * The update hook raises the shipped default of 2 to 4.
   */
  public function testUpdateRaisesLimitFromDefault(): void {
    \Drupal::configFactory()->getEditable('facet_bot_blocker.settings')
      ->set('facets_bot_blocker_limit', 2)
      ->save();

    mukurtu_bot_protection_update_40001();

    $this->assertSame(4, \Drupal::config('facet_bot_blocker.settings')->get('facets_bot_blocker_limit'));
  }

  /**
   * The update hook leaves a site-customized limit alone.
   */
  public function testUpdateLeavesCustomLimitAlone(): void {
    \Drupal::configFactory()->getEditable('facet_bot_blocker.settings')
      ->set('facets_bot_blocker_limit', 10)
      ->save();

    mukurtu_bot_protection_update_40001();

    $this->assertSame(10, \Drupal::config('facet_bot_blocker.settings')->get('facets_bot_blocker_limit'));
  }

  /**
   * The update hook is a no-op without config.
   */
  public function testUpdateIsNoOpWithoutConfig(): void {
    $this->assertTrue(\Drupal::config('facet_bot_blocker.settings')->isNew());
    mukurtu_bot_protection_update_40001();
    $this->assertTrue(\Drupal::config('facet_bot_blocker.settings')->isNew());
  }

}

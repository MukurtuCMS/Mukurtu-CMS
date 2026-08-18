<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40085(), which adds the Security block to the
 * Mukurtu admin dashboard.
 *
 * @see mukurtu_core_update_40085()
 */
#[Group('mukurtu_core')]
class DashboardSecurityBlockUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The hook writes a partial dashboards config fixture, not full schema data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The uuid the update hook uses for the Security block component.
   */
  protected const UUID = '3f8b6c1a-2e4d-4a9f-9c7b-5d1e8a2f6b3c';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * Saves a minimal dashboard config fixture, mirroring the hook's shape.
   */
  protected function saveDashboardConfig(): void {
    \Drupal::configFactory()->getEditable('dashboards.dashboard.mukurtu_dashboard')
      ->setData([
        'id' => 'mukurtu_dashboard',
        'sections' => [
          [
            'components' => [],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The update hook adds the block once the dashboard-security menu exists.
   */
  public function testUpdateAddsBlockWhenMenuExists(): void {
    \Drupal::entityTypeManager()->getStorage('menu')->create([
      'id' => 'dashboard-security',
      'label' => 'Security',
    ])->save();
    $this->saveDashboardConfig();

    mukurtu_core_update_40085();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $this->assertArrayHasKey(self::UUID, $components);
    $this->assertEquals('system_menu_block:dashboard-security', $components[self::UUID]['configuration']['id']);
  }

  /**
   * The update hook is a no-op when the dashboard-security menu is missing,
   * so it never writes a block reference the plugin can't resolve.
   */
  public function testUpdateSkipsBlockWhenMenuMissing(): void {
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('menu')->load('dashboard-security'));
    $this->saveDashboardConfig();

    mukurtu_core_update_40085();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $this->assertSame([], $components);
  }

  /**
   * The update hook is a no-op when the dashboard config doesn't exist.
   */
  public function testUpdateIsNoOpWithoutDashboardConfig(): void {
    \Drupal::entityTypeManager()->getStorage('menu')->create([
      'id' => 'dashboard-security',
      'label' => 'Security',
    ])->save();
    $this->assertTrue(\Drupal::config('dashboards.dashboard.mukurtu_dashboard')->isNew());

    mukurtu_core_update_40085();

    $this->assertTrue(\Drupal::config('dashboards.dashboard.mukurtu_dashboard')->isNew());
  }

  /**
   * The update hook doesn't duplicate the component if it's already present.
   */
  public function testUpdateIsIdempotent(): void {
    \Drupal::entityTypeManager()->getStorage('menu')->create([
      'id' => 'dashboard-security',
      'label' => 'Security',
    ])->save();
    \Drupal::configFactory()->getEditable('dashboards.dashboard.mukurtu_dashboard')
      ->setData([
        'id' => 'mukurtu_dashboard',
        'sections' => [
          [
            'components' => [
              self::UUID => [
                'uuid' => self::UUID,
                'region' => 'three',
                'configuration' => [
                  'id' => 'system_menu_block:dashboard-security',
                ],
                'weight' => 9,
              ],
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40085();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $this->assertCount(1, $components);
  }

}

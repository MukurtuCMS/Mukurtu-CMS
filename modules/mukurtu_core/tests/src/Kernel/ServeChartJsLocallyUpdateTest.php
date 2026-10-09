<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40212().
 *
 * @see mukurtu_core_update_40212()
 */
#[Group('mukurtu_core')]
class ServeChartJsLocallyUpdateTest extends KernelTestBase {

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
    // EnableChartsForVisitorsUpdateTest: mukurtu_core is not enabled here.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * The charts CDN option is turned off.
   */
  public function testTurnsOffCdn(): void {
    \Drupal::service('module_installer')->install(['charts_chartjs']);
    $this->assertTrue(
      \Drupal::config('charts.settings')->get('advanced.requirements.cdn'),
      'Precondition: charts no longer ships with its CDN option on.'
    );

    $message = mukurtu_core_update_40212();

    $this->assertFalse(\Drupal::config('charts.settings')->get('advanced.requirements.cdn'));
    $this->assertNotNull($message);
  }

  /**
   * The Klaro service 40202 registered for Chart.js is removed.
   */
  public function testRemovesKlaroService(): void {
    \Drupal::service('module_installer')->install(['charts_chartjs', 'klaro']);
    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $storage->create([
      'id' => 'charts_chartjs',
      'label' => 'Charts (visitor reports)',
      'required' => TRUE,
      'javascripts' => ['unpkg.com/chart'],
    ])->save();

    mukurtu_core_update_40212();

    $this->assertNull(
      \Drupal::entityTypeManager()->getStorage('klaro_app')->load('charts_chartjs'),
      'The Klaro service for Chart.js is still there.'
    );
  }

  /**
   * A site without charts_chartjs or Klaro is left alone, and says nothing.
   */
  public function testNoOpWithoutModules(): void {
    $this->assertNull(mukurtu_core_update_40212());
    $this->assertTrue(\Drupal::config('charts.settings')->isNew());
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    \Drupal::service('module_installer')->install(['charts_chartjs', 'klaro']);

    $this->assertNotNull(mukurtu_core_update_40212());
    $this->assertNull(mukurtu_core_update_40212(), 'The second run reported work that had already been done.');
  }

}

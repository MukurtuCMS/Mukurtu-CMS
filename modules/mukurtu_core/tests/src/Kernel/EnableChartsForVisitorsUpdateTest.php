<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40202().
 *
 * Installing the "visitors" module for real (via ModuleInstallerInterface,
 * not the $modules property - see
 * feedback_kerneltestbase_modules_no_real_install in project memory) also
 * installs charts/charts_chartjs, since visitors.info.yml now depends on
 * charts:charts_chartjs. It does not install visitors_geoip, which depends
 * on visitors rather than the other way around. That gap is exactly the
 * shape mukurtu_core_update_40088() left behind on a site that updated past
 * the 4.0.1 hook strip without ever running it: visitors present, charting
 * backend and visitors_geoip missing.
 *
 * @see mukurtu_core_update_40202()
 */
#[Group('mukurtu_core')]
class EnableChartsForVisitorsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'path', 'field', 'options'];

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
   * A site with visitors enabled but the charting backend missing is fixed.
   */
  public function testInstallsMissingModulesAndSetsLibrary(): void {
    \Drupal::service('module_installer')->install(['visitors']);

    $module_handler = \Drupal::moduleHandler();
    $this->assertTrue($module_handler->moduleExists('charts'), 'Precondition: visitors no longer depends on charts_chartjs, so this test is not exercising the intended gap.');
    $this->assertTrue($module_handler->moduleExists('charts_chartjs'));
    $this->assertFalse($module_handler->moduleExists('visitors_geoip'), 'Precondition: visitors_geoip is already enabled, so installing it here would prove nothing.');

    $message = mukurtu_core_update_40202();

    $module_handler = \Drupal::moduleHandler();
    $this->assertTrue($module_handler->moduleExists('visitors_geoip'), 'visitors_geoip was not installed.');
    $this->assertSame(
      'chartjs',
      \Drupal::config('charts.settings')->get('charts_default_settings.library'),
      'The default charting library was not set to chartjs.'
    );
    $this->assertNotNull($message, 'The operator was told nothing about modules being installed.');
    $this->assertStringContainsString('visitors_geoip', (string) $message);
  }

  /**
   * A site without visitors is left alone, and says nothing.
   */
  public function testNoOpWhenVisitorsNotEnabled(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('visitors'));

    $this->assertNull(mukurtu_core_update_40202());

    $module_handler = \Drupal::moduleHandler();
    $this->assertFalse($module_handler->moduleExists('charts'));
    $this->assertFalse($module_handler->moduleExists('visitors_geoip'));
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    \Drupal::service('module_installer')->install(['visitors']);

    $first = mukurtu_core_update_40202();
    $second = mukurtu_core_update_40202();

    $this->assertNotNull($first);
    $this->assertNull($second, 'The second run reported work that had already been done.');
  }

  /**
   * No Klaro service is registered for Chart.js any more.
   *
   * Chart.js is served from the profile's own libraries/ directory now, so
   * there is no third-party script for a Klaro service to cover, and the
   * config file this hook used to read it from is gone.
   *
   * @see mukurtu_core_update_40212()
   */
  public function testDoesNotRegisterKlaroService(): void {
    \Drupal::service('module_installer')->install(['visitors', 'klaro']);

    mukurtu_core_update_40202();

    $this->assertNull(
      \Drupal::entityTypeManager()->getStorage('klaro_app')->load('charts_chartjs'),
      'A Klaro service for Chart.js was created.'
    );
  }

  /**
   * Visitors report pages are added to Klaro's disable_urls.
   *
   * Klaro's hook_js_alter() rewrites scripts it matches into placeholders,
   * and nothing re-triggers Drupal.attachBehaviors() for an AJAX-inserted
   * placeholder to resolve it. disable_urls is what stops the rewriting.
   */
  public function testAddsVisitorsToKlaroDisabledUrls(): void {
    \Drupal::service('module_installer')->install(['visitors', 'klaro']);

    mukurtu_core_update_40202();

    $this->assertContains(
      '^\/visitors',
      \Drupal::config('klaro.settings')->get('disable_urls'),
      'Klaro will still block scripts on the Visitors report pages.'
    );
  }

  /**
   * Running it twice does not duplicate the disable_urls entry.
   */
  public function testDisabledUrlsEntryNotDuplicated(): void {
    \Drupal::service('module_installer')->install(['visitors', 'klaro']);

    mukurtu_core_update_40202();
    mukurtu_core_update_40202();

    $disable_urls = \Drupal::config('klaro.settings')->get('disable_urls');
    $this->assertCount(1, array_keys($disable_urls, '^\/visitors', TRUE));
  }

  /**
   * A site without Klaro is not touched, and does not error.
   */
  public function testSkipsKlaroWhenNotEnabled(): void {
    \Drupal::service('module_installer')->install(['visitors']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('klaro'));

    // Asserting no exception is the point: getStorage('klaro_app') would
    // throw a PluginNotFoundException if the guard were missing.
    mukurtu_core_update_40202();
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('klaro'));
  }

}

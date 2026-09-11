<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\UpdatePathRequirements;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the 4.0.1 update path guard actually stops an out-of-date site.
 *
 * This is the one piece of 4.0.1 that has no safety net behind it. Stripping
 * the update hooks is safe for fresh installs, because they never ran them,
 * and safe for sites already on 4.0.0, because they have nothing left to run.
 * The site that is neither is caught only by this guard: core will happily
 * report "no pending updates" to a site several releases behind, since
 * update_get_update_list() has no hooks left to list.
 *
 * So the assertion that matters is not that the guard exists, but that it
 * fires. The schema version is rolled backwards here to manufacture exactly
 * the state a beta-era site would arrive in.
 *
 * @see \Drupal\mukurtu_core\Hook\UpdatePathRequirements
 * @see mukurtu_core_update_requirements()
 */
#[Group('mukurtu_core')]
class UpdatePathGuardTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * The update hook registry.
   */
  private UpdateHookRegistry $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    $this->registry = $this->container->get('update.update_hook_registry');
    \Drupal::moduleHandler()->loadInclude('mukurtu_core', 'install');
  }

  /**
   * A module that is not installed is skipped rather than flagged.
   *
   * Note that this is also the state a kernel test itself is in.
   * KernelTestBase enables modules directly instead of going through
   * ModuleInstaller, so no schema version is ever written and the registry
   * reports SCHEMA_UNINSTALLED (-1) for every module here. That is precisely
   * why the seeding behaviour this release depends on cannot be asserted in a
   * kernel test, and is verified against a real site install instead.
   *
   * Skipping matters on its own account: -1 is numerically below every
   * last_removed value, so treating it as "behind" would block a site for
   * every Mukurtu module it does not have installed.
   */
  public function testUninstalledModulesAreSkipped(): void {
    $this->assertSame(
      UpdateHookRegistry::SCHEMA_UNINSTALLED,
      $this->registry->getInstalledVersion('mukurtu_core'),
      'Precondition: kernel tests do not seed a schema version.'
    );

    $this->assertSame([], UpdatePathRequirements::modulesBehind(
      \Drupal::moduleHandler(),
      $this->registry
    ));
    $this->assertSame([], mukurtu_core_update_requirements());
  }

  /**
   * A site that has finished the 4.0.0 updates is not flagged.
   */
  public function testUpToDateSiteIsNotBlocked(): void {
    $this->registry->setInstalledVersion('mukurtu_core', mukurtu_core_update_last_removed());

    $this->assertSame([], UpdatePathRequirements::modulesBehind(
      \Drupal::moduleHandler(),
      $this->registry
    ));
    $this->assertSame([], mukurtu_core_update_requirements());
  }

  /**
   * A site left behind by the strip is blocked with an error.
   *
   * Error severity is what actually stops update.php and drush updb, so it is
   * asserted explicitly rather than merely checking that a message appears.
   */
  public function testOutOfDateSiteIsBlocked(): void {
    $lastRemoved = mukurtu_core_update_last_removed();
    $this->registry->setInstalledVersion('mukurtu_core', $lastRemoved - 5);

    $behind = UpdatePathRequirements::modulesBehind(\Drupal::moduleHandler(), $this->registry);
    $this->assertArrayHasKey('mukurtu_core', $behind);
    $this->assertSame($lastRemoved - 5, $behind['mukurtu_core']['installed']);
    $this->assertSame($lastRemoved, $behind['mukurtu_core']['required']);

    $requirements = mukurtu_core_update_requirements();
    $this->assertArrayHasKey('mukurtu_core_update_path', $requirements);

    $requirement = $requirements['mukurtu_core_update_path'];
    $this->assertSame(
      RequirementSeverity::Error,
      $requirement['severity'],
      'Anything below error severity would let the update proceed.'
    );

    $description = (string) $requirement['description'];
    $this->assertStringContainsString('mukurtu_core', $description, 'The operator is not told which module is behind.');
    $this->assertStringContainsString((string) ($lastRemoved - 5), $description, 'The description omits the version the site is on.');
    $this->assertStringContainsString((string) $lastRemoved, $description, 'The description omits the version the site needs.');
  }

  /**
   * The status report carries the same finding.
   *
   * Someone can update the code and never run update.php at all, in which case
   * they never see the update phase message. The runtime check is the only
   * thing that surfaces the problem for them.
   */
  public function testStatusReportAlsoReportsIt(): void {
    $this->registry->setInstalledVersion('mukurtu_core', mukurtu_core_update_last_removed() - 1);

    $hook = new UpdatePathRequirements(\Drupal::moduleHandler(), $this->registry);
    $requirements = $hook->runtimeRequirements();

    $this->assertArrayHasKey('mukurtu_core_update_path', $requirements);
    $this->assertSame(RequirementSeverity::Error, $requirements['mukurtu_core_update_path']['severity']);
  }

  /**
   * An exactly-current site is not flagged.
   *
   * The boundary matters: last_removed is the version a site lands on after
   * running the final 4.0.0 update, so treating "equal" as behind would block
   * every correctly updated site.
   */
  public function testBoundaryVersionIsNotBlocked(): void {
    $this->registry->setInstalledVersion('mukurtu_core', mukurtu_core_update_last_removed());

    $this->assertSame([], UpdatePathRequirements::modulesBehind(
      \Drupal::moduleHandler(),
      $this->registry
    ));
  }

  /**
   * The rendered module list names versions in a readable way.
   */
  public function testModuleListFormatting(): void {
    $this->assertSame(
      'mukurtu_core (at 40118, needs 40123), mukurtu_protocol (at 40041, needs 40045)',
      UpdatePathRequirements::formatModuleList([
        'mukurtu_core' => ['installed' => 40118, 'required' => 40123],
        'mukurtu_protocol' => ['installed' => 40041, 'required' => 40045],
      ])
    );
  }

}

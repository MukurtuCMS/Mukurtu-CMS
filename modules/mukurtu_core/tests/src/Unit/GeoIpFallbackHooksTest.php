<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Hook\GeoIpFallbackHooks;
use Drupal\mukurtu_core\Service\DbIpDownloadService;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use Drupal\mukurtu_core\Service\VisitorsLocationBackfill;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the visitor geolocation status report entry and settings form.
 *
 * @see \Drupal\mukurtu_core\Hook\GeoIpFallbackHooks
 */
#[Group('mukurtu_core')]
class GeoIpFallbackHooksTest extends UnitTestCase {

  /**
   * Builds the hooks around a stub database and backfill.
   *
   * @param object|null $metadata
   *   The geolocation database metadata, or NULL for no database.
   * @param int|null $remaining
   *   Addresses the backfill has left, or NULL when it is not running.
   */
  private function hooks(?object $metadata, ?int $remaining = NULL): GeoIpFallbackHooks {
    $backfill = $this->createMock(VisitorsLocationBackfill::class);
    $backfill->method('databaseMetadata')->willReturn($metadata);
    $backfill->method('remaining')->willReturn($remaining);
    $date_formatter = $this->createMock(DateFormatterInterface::class);
    $date_formatter->method('format')->willReturn('30 Sep 2026');

    $hooks = new GeoIpFallbackHooks(
      $this->createMock(DbIpDownloadService::class),
      $this->createMock(ModuleHandlerInterface::class),
      $backfill,
      $this->getConfigFactoryStub([VisitorsCountryMap::CONFIG_NAME => ['city_threshold' => 5]]),
      $date_formatter,
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * A status report as visitors_geoip leaves it.
   */
  private function requirements(?RequirementSeverity $severity = RequirementSeverity::OK): array {
    return [
      'visitors_geoip' => [
        'title' => 'Visitors GeoIP',
        'value' => 'Type: DBIP-City-Lite build: 2026-09-30',
        'severity' => $severity,
      ],
    ];
  }

  /**
   * With a database, the entry names the provider and its date.
   */
  public function testInstalledDatabaseIsNamed(): void {
    $requirements = $this->requirements();
    $this->hooks((object) ['databaseType' => 'DBIP-City-Lite', 'buildEpoch' => 1790000000])->runtimeRequirementsAlter($requirements);

    $this->assertSame('Visitor location database', (string) $requirements['visitors_geoip']['title']);
    $this->assertSame('DB-IP Lite, updated 30 Sep 2026', $requirements['visitors_geoip']['value']);
    $this->assertSame(RequirementSeverity::OK, $requirements['visitors_geoip']['severity']);
  }

  /**
   * MaxMind's database is named as such.
   */
  public function testMaxMindDatabaseIsNamed(): void {
    $requirements = $this->requirements();
    $this->hooks((object) ['databaseType' => 'GeoLite2-City', 'buildEpoch' => 1790000000])->runtimeRequirementsAlter($requirements);

    $this->assertStringStartsWith('MaxMind GeoLite2, ', $requirements['visitors_geoip']['value']);
  }

  /**
   * The missing-extension warning from visitors_geoip keeps its explanation.
   */
  public function testExtensionWarningIsKept(): void {
    $requirements = $this->requirements(RequirementSeverity::Warning);
    $this->hooks((object) ['databaseType' => 'DBIP-City-Lite', 'buildEpoch' => 0])->runtimeRequirementsAlter($requirements);

    $this->assertStringContainsString('The maxminddb PHP extension is not installed.', $requirements['visitors_geoip']['value']);
    $this->assertSame(RequirementSeverity::Warning, $requirements['visitors_geoip']['severity']);
  }

  /**
   * The legacy integer severity visitors_geoip actually sends works too.
   *
   * Core converts legacy severities to the enum only after the alter runs.
   */
  public function testLegacyIntegerWarningIsRecognised(): void {
    $requirements = $this->requirements();
    $requirements['visitors_geoip']['severity'] = 1;
    $this->hooks((object) ['databaseType' => 'DBIP-City-Lite', 'buildEpoch' => 0])->runtimeRequirementsAlter($requirements);

    $this->assertStringContainsString('The maxminddb PHP extension is not installed.', $requirements['visitors_geoip']['value']);
  }

  /**
   * Without a database, the entry warns and says what that means.
   */
  public function testMissingDatabaseWarns(): void {
    $requirements = $this->requirements();
    $this->hooks(NULL)->runtimeRequirementsAlter($requirements);

    $this->assertStringStartsWith('No location database is installed', $requirements['visitors_geoip']['value']);
    $this->assertSame(RequirementSeverity::Warning, $requirements['visitors_geoip']['severity']);
  }

  /**
   * A running backfill reports how much is left.
   */
  public function testRunningBackfillIsReported(): void {
    $requirements = $this->requirements();
    $this->hooks((object) ['databaseType' => 'DBIP-City-Lite', 'buildEpoch' => 0], 12)->runtimeRequirementsAlter($requirements);

    $this->assertStringEndsWith('Updating locations for past visits. 12 addresses remaining.', $requirements['visitors_geoip']['value']);
  }

  /**
   * Without visitors_geoip there is no entry, and none is added.
   */
  public function testNoEntryWithoutVisitorsGeoip(): void {
    $requirements = [];
    $this->hooks(NULL)->runtimeRequirementsAlter($requirements);

    $this->assertSame([], $requirements);
  }

  /**
   * The settings form gets the threshold, and still saves its own settings.
   */
  public function testSettingsFormGetsTheThreshold(): void {
    $form = ['license' => ['#type' => 'textfield', '#description' => 'Get a key from MaxMind.']];
    $this->hooks(NULL)->formVisitorsGeoipAdminFormAlter($form, new FormState());

    $this->assertSame(5, $form['mukurtu_city_threshold']['#default_value']);
    $this->assertSame(1, $form['mukurtu_city_threshold']['#min']);
    $this->assertSame(['::submitForm', [GeoIpFallbackHooks::class, 'saveCityThreshold']], $form['#submit']);
    $this->assertSame('Get a key from MaxMind.', $form['license']['#description']['#context']['contrib']);
  }

}

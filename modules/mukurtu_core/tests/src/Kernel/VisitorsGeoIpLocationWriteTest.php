<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use Drupal\visitors\Controller\Visitors;
use Drupal\visitors_geoip\VisitorsGeoIpInterface;
use GeoIp2\Model\City;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a DB-IP-resolved visit can actually be written to the database.
 *
 * Drives contrib's own Visitors::doLocation() and inserts its fields into the
 * real visitors table, which is the path a tracking request takes. DB-IP City
 * Lite records have no subdivision ISO code, which contrib copied into the
 * NOT NULL location_region column, so every visit DB-IP could place failed
 * with an integrity constraint violation and was never recorded.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService::normalize()
 */
#[Group('mukurtu_core')]
class VisitorsGeoIpLocationWriteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'path', 'field', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Installed for real rather than listed in $modules: visitors_geoip adds
    // the location_region/city/latitude/longitude columns in hook_install(),
    // which $modules does not run.
    \Drupal::service('module_installer')->install(['visitors', 'visitors_geoip']);
  }

  /**
   * A DB-IP City Lite record, as its reader returns one for 8.8.8.8.
   */
  private function dbIpRecord(): City {
    return new City([
      'continent' => ['code' => 'NA'],
      'country' => ['iso_code' => 'US'],
      'subdivisions' => [['names' => ['en' => 'California']]],
      'city' => ['names' => ['en' => 'Mountain View']],
      'location' => ['latitude' => 37.422, 'longitude' => -122.085],
    ]);
  }

  /**
   * Runs contrib's doLocation() with a given lookup service and writes a row.
   */
  private function trackVisit(VisitorsGeoIpInterface $lookup): void {
    $this->container->set('visitors_geoip.lookup', $lookup);
    $controller = Visitors::create($this->container);

    $fields = [];
    (new \ReflectionMethod($controller, 'doLocation'))->invokeArgs($controller, [&$fields, '8.8.8.8', []]);

    $this->container->get('database')->insert('visitors')
      ->fields($fields + $this->requiredFields())
      ->execute();
  }

  /**
   * Values for the visitors columns that have no default, besides location.
   *
   * Read from contrib's own schema so the test keeps inserting a valid row
   * if visitors adds another required column.
   */
  private function requiredFields(): array {
    $this->container->get('module_handler')->loadInclude('visitors', 'install');
    $fields = [];
    foreach (visitors_schema()['visitors']['fields'] as $column => $spec) {
      if (!empty($spec['not null']) && !array_key_exists('default', $spec) && $spec['type'] !== 'serial') {
        $fields[$column] = in_array($spec['type'], ['int', 'float', 'numeric'], TRUE) ? 0 : '';
      }
    }
    $fields['visitors_ip'] = '8.8.8.8';
    $fields['visitors_date_time'] = \Drupal::time()->getRequestTime();
    return $fields;
  }

  /**
   * Without normalization, the raw DB-IP record cannot be written.
   *
   * The negative control: proves the test exercises the real failure. If this
   * starts failing, visitors_geoip has fixed the NULL write itself and
   * DbIpFallbackGeoIpService::normalize() may no longer be needed.
   */
  public function testRawDbIpRecordFailsToWrite(): void {
    $raw_lookup = $this->createMock(VisitorsGeoIpInterface::class);
    $raw_lookup->method('city')->willReturn($this->dbIpRecord());

    $this->expectException(IntegrityConstraintViolationException::class);
    $this->expectExceptionMessage('location_region');
    $this->trackVisit($raw_lookup);
  }

  /**
   * Through the fallback service, the visit is written with its location.
   */
  public function testDbIpRecordIsWrittenWithRegionAndCity(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturn($this->dbIpRecord());
    $locator = $this->createMock(DbIpDatabaseLocator::class);
    $locator->method('realpath')->willReturn(NULL);

    $this->trackVisit(new DbIpFallbackGeoIpService($inner, $locator));

    $row = $this->container->get('database')
      ->query('SELECT location_continent, location_country, location_region, location_city, location_latitude FROM {visitors}')
      ->fetchAssoc();
    $this->assertSame('NA', $row['location_continent']);
    $this->assertSame('US', $row['location_country']);
    $this->assertSame('California', $row['location_region']);
    $this->assertSame('Mountain View', $row['location_city']);
    $this->assertEqualsWithDelta(37.422, (float) $row['location_latitude'], 0.0001);
  }

}

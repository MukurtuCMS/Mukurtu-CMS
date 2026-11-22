<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use Drupal\visitors_geoip\GeoIpCity;
use Drupal\visitors_geoip\VisitorsGeoIpInterface;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DB-IP fallback's delegation logic.
 *
 * Only the paths that do not need a real MMDB file are covered here: when
 * the inner (MaxMind) service already has an answer, and when no DB-IP
 * database is present on disk. The successful "DB-IP itself resolves the
 * address" path was instead verified manually against a real downloaded
 * database (see docs/visitors-geoip-setup.md); GeoIp2\Database\Reader reads
 * directly from a binary file and cannot usefully be mocked or faked with a
 * tiny fixture.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 */
#[Group('mukurtu_core')]
class DbIpFallbackGeoIpServiceTest extends UnitTestCase {

  /**
   * Builds the service with a given inner service.
   *
   * The locator reports no DB-IP file present in any of these tests,
   * matching a site that has not downloaded one (yet).
   */
  private function fallbackService(VisitorsGeoIpInterface $inner): DbIpFallbackGeoIpService {
    $locator = $this->createMock(DbIpDatabaseLocator::class);
    $locator->method('realpath')->willReturn(NULL);

    return new DbIpFallbackGeoIpService($inner, $locator);
  }

  /**
   * When the inner (MaxMind) service resolves the address, its record wins.
   *
   * DB-IP is never consulted: the mocked inner service is the only thing
   * that could produce this exact object.
   */
  public function testInnerRecordIsReturnedWithoutTouchingDbIp(): void {
    $record = new \stdClass();

    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturn($record);

    $this->assertSame($record, $this->fallbackService($inner)->city('8.8.8.8'));
  }

  /**
   * With no MaxMind result and no DB-IP file on disk, the answer is NULL.
   *
   * Not an exception: a site that has neither configured MaxMind nor yet
   * had cron/install download the DB-IP fallback should behave exactly like
   * a site with only MaxMind configured and unresolvable -- NULL, so
   * callers that already handle "no location" for that case keep working.
   */
  public function testNoInnerRecordAndNoDbIpFileReturnsNull(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturn(NULL);

    $this->assertNull($this->fallbackService($inner)->city('203.0.113.1'));
  }

  /**
   * A DB-IP City Lite record's region name stands in for its missing code.
   *
   * This raw shape is exactly what DB-IP City Lite returns (checked against
   * the 2026-09 database for 8.8.8.8): subdivision names, no iso_code.
   */
  public function testDbIpRecordGetsItsRegionNameAsTheRegionCode(): void {
    $record = DbIpFallbackGeoIpService::normalize(new City([
      'continent' => ['code' => 'NA'],
      'country' => ['iso_code' => 'US'],
      'subdivisions' => [['names' => ['en' => 'California']]],
      'city' => ['names' => ['en' => 'Mountain View']],
      'location' => ['latitude' => 37.422, 'longitude' => -122.085],
    ]));

    $this->assertInstanceOf(GeoIpCity::class, $record);
    $this->assertSame('California', $record->regionCode);
    $this->assertSame('Mountain View', $record->city);
    $this->assertSame('NA', $record->continentCode);
    $this->assertSame('US', $record->countryCode);
    $this->assertSame(37.422, $record->latitude);
    $this->assertSame(-122.085, $record->longitude);
  }

  /**
   * A record that already has every field (MaxMind's usual case) is kept.
   */
  public function testCompleteRecordIsUnchanged(): void {
    $record = DbIpFallbackGeoIpService::normalize(new City([
      'continent' => ['code' => 'NA'],
      'country' => ['iso_code' => 'US'],
      'subdivisions' => [['iso_code' => 'CA', 'names' => ['en' => 'California']]],
      'city' => ['names' => ['en' => 'Mountain View', 'de' => 'Mountain View']],
    ]));

    $this->assertSame('CA', $record->regionCode);
    $this->assertSame('Mountain View', $record->city);
  }

  /**
   * A country-only record gets empty strings, never NULL, for the rest.
   *
   * Both MaxMind and DB-IP return records like this for some addresses
   * (country-level allocations, many anycast and mobile ranges).
   */
  public function testMissingFieldsBecomeEmptyStrings(): void {
    $record = DbIpFallbackGeoIpService::normalize(new City([
      'registered_country' => ['iso_code' => 'SG'],
    ]));

    $this->assertSame('', $record->continentCode);
    $this->assertSame('SG', $record->countryCode, 'A missing country should fall back to the registered country.');
    $this->assertSame('', $record->regionCode);
    $this->assertSame('', $record->city);
    $this->assertNull($record->latitude);
  }

  /**
   * A city with no English name uses its name in another language.
   */
  public function testCityWithoutEnglishNameUsesAnotherName(): void {
    $record = DbIpFallbackGeoIpService::normalize(new City([
      'subdivisions' => [['names' => ['ja' => '東京都']]],
      'city' => ['names' => ['ja' => '東京']],
    ]));

    $this->assertSame('東京都', $record->regionCode);
    $this->assertSame('東京', $record->city);
  }

  /**
   * The inner (MaxMind) service's GeoIpCity results are normalized too.
   *
   * The visitors_geoip GeoIpService returns a GeoIpCity whose missing values
   * are NULL; they become empty strings, and the rest is kept.
   */
  public function testInnerRecordIsNormalized(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturn(new GeoIpCity(NULL, 'FR', NULL, NULL, 48.86, 2.35));

    $record = $this->fallbackService($inner)->city('8.8.8.8');

    $this->assertInstanceOf(GeoIpCity::class, $record);
    $this->assertSame('', $record->continentCode);
    $this->assertSame('FR', $record->countryCode);
    $this->assertSame('', $record->regionCode);
    $this->assertSame('', $record->city);
    $this->assertSame(48.86, $record->latitude);
    $this->assertSame(2.35, $record->longitude);
  }

  /**
   * An inner lookup that throws is treated as "no result", not a crash.
   *
   * The GeoIpService in visitors_geoip throws AddressNotFoundException for
   * private and unknown addresses (https://www.drupal.org/i/3618588).
   */
  public function testInnerLookupExceptionIsTreatedAsNoResult(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willThrowException(new AddressNotFoundException('The address 10.0.0.1 is not in the database.'));

    $this->assertNull($this->fallbackService($inner)->city('10.0.0.1'));
  }

  /**
   * Environment checks pass straight through to the inner service.
   *
   * These describe the PHP environment (the geoip2 library, the maxminddb
   * extension), not which specific database is in use, so there is nothing
   * DB-IP-specific to add here.
   */
  public function testEnvironmentChecksDelegateToTheInnerService(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->expects($this->once())->method('hasLibrary')->with('Some\Class')->willReturn(TRUE);
    $inner->expects($this->once())->method('hasExtension')->with('maxminddb')->willReturn(FALSE);

    $service = $this->fallbackService($inner);
    $this->assertTrue($service->hasLibrary('Some\Class'));
    $this->assertFalse($service->hasExtension('maxminddb'));
  }

}

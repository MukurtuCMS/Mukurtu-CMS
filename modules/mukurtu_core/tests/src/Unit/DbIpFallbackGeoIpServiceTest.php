<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
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

    $this->assertSame('California', $record->subdivisions[0]->isoCode);
    $this->assertSame('Mountain View', $record->city->names['en']);
    $this->assertSame('NA', $record->continent->code);
    $this->assertSame('US', $record->country->isoCode);
    $this->assertSame(37.422, $record->location->latitude);
    $this->assertSame(-122.085, $record->location->longitude);
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

    $this->assertSame('CA', $record->subdivisions[0]->isoCode);
    $this->assertSame('Mountain View', $record->city->names['en']);
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

    $this->assertSame('', $record->continent->code);
    $this->assertSame('SG', $record->country->isoCode, 'A missing country should fall back to the registered country.');
    $this->assertSame('', $record->subdivisions[0]->isoCode);
    $this->assertSame('', $record->city->names['en']);
  }

  /**
   * A city with no English name uses its name in another language.
   */
  public function testCityWithoutEnglishNameUsesAnotherName(): void {
    $record = DbIpFallbackGeoIpService::normalize(new City([
      'subdivisions' => [['names' => ['ja' => '東京都']]],
      'city' => ['names' => ['ja' => '東京']],
    ]));

    $this->assertSame('東京都', $record->subdivisions[0]->isoCode);
    $this->assertSame('東京', $record->city->names['en']);
  }

  /**
   * The inner (MaxMind) service's records are normalized too.
   */
  public function testInnerRecordIsNormalized(): void {
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturn(new City(['country' => ['iso_code' => 'FR']]));

    $record = $this->fallbackService($inner)->city('8.8.8.8');

    $this->assertSame('FR', $record->country->isoCode);
    $this->assertSame('', $record->subdivisions[0]->isoCode);
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
   * hasLibrary()/hasExtension() pass straight through to the inner service.
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

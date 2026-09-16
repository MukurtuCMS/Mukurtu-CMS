<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use Drupal\visitors_geoip\VisitorsGeoIpInterface;
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
   * Builds the service with a given inner service and geoip_path.
   */
  private function fallbackService(VisitorsGeoIpInterface $inner, ?string $geoip_path = '/geoip'): DbIpFallbackGeoIpService {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('geoip_path')->willReturn($geoip_path);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('visitors_geoip.settings')->willReturn($config);

    $file_system = $this->createMock(FileSystemInterface::class);
    // No real DB-IP file in any of these tests: realpath() reports nothing
    // there, matching a site that has not downloaded one (yet).
    $file_system->method('realpath')->willReturn(FALSE);

    return new DbIpFallbackGeoIpService($inner, $config_factory, $file_system);
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

<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Service\DbIpDownloadService;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the DB-IP download service.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 */
#[Group('mukurtu_core')]
class DbIpDownloadServiceTest extends UnitTestCase {

  /**
   * Files created by a test, cleaned up in tearDown().
   *
   * @var string[]
   */
  private array $tempFiles = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->tempFiles as $path) {
      @unlink($path);
    }
    parent::tearDown();
  }

  /**
   * Tracks a path for cleanup and returns it.
   */
  private function tempPath(string $suffix): string {
    $path = sys_get_temp_dir() . '/dbip_test_' . uniqid() . $suffix;
    $this->tempFiles[] = $path;
    return $path;
  }

  /**
   * Builds the service with mocked collaborators.
   *
   * @param \Drupal\Core\File\FileSystemInterface|null $file_system
   *   A pre-configured file system mock, or NULL for a simple default.
   */
  private function downloadService(ClientInterface $http_client, ?FileSystemInterface $file_system = NULL): DbIpDownloadService {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('geoip_path')->willReturn('/geoip');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('visitors_geoip.settings')->willReturn($config);

    $file_system ??= $this->createMock(FileSystemInterface::class);
    $logger = $this->createMock(LoggerInterface::class);

    return new DbIpDownloadService($http_client, $config_factory, $file_system, $logger);
  }

  /**
   * No file at all counts as stale, the same as a site that never downloaded one.
   */
  public function testMissingDatabaseIsStale(): void {
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturn(FALSE);

    $service = $this->downloadService($this->createMock(ClientInterface::class), $file_system);
    $this->assertTrue($service->isStale());
  }

  /**
   * A database modified moments ago is not due for a refresh.
   */
  public function testFreshDatabaseIsNotStale(): void {
    $path = $this->tempPath('.mmdb');
    file_put_contents($path, 'not a real database, just needs to exist');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturn($path);

    $service = $this->downloadService($this->createMock(ClientInterface::class), $file_system);
    $this->assertFalse($service->isStale());
  }

  /**
   * A database older than DB-IP's monthly release cycle is due for a refresh.
   */
  public function testOldDatabaseIsStale(): void {
    $path = $this->tempPath('.mmdb');
    file_put_contents($path, 'not a real database, just needs to exist');
    touch($path, time() - (40 * 86400));

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturn($path);

    $service = $this->downloadService($this->createMock(ClientInterface::class), $file_system);
    $this->assertTrue($service->isStale());
  }

  /**
   * A successful download decompresses the gzip payload into place.
   *
   * Stands in for DB-IP's server: the mocked HTTP client writes a small
   * known gzip payload to whatever 'sink' path download() asks it to, the
   * same contract Guzzle's real client fulfils. This exercises the actual
   * streaming gzip decompression this class does, just against a payload
   * far smaller than a real ~60MB monthly download.
   *
   * Padded past installDecompressed()'s 10MB minimum-size sanity check
   * (guarding against installing a truncated download) with a repeated
   * byte, which a real MMDB file's contents never would be -- but which
   * gzip compresses back down to a few dozen bytes, so this test pads out
   * the decompressed size without actually downloading or holding 10MB+ of
   * meaningfully different data.
   */
  public function testSuccessfulDownloadDecompressesToTheDestination(): void {
    $original = "this stands in for a real MMDB file's bytes" . str_repeat('a', 11 * 1024 * 1024);
    $gz_payload = gzencode($original);

    $destination_dir = $this->tempPath('_dir');
    mkdir($destination_dir);
    $destination = $destination_dir . '/' . DbIpFallbackGeoIpService::FILENAME;
    $this->tempFiles[] = $destination;

    $http_client = $this->createMock(ClientInterface::class);
    $http_client->method('request')
      ->willReturnCallback(function (string $method, string $uri, array $options) use ($gz_payload) {
        file_put_contents($options['sink'], $gz_payload);
        return $this->createMock(\Psr\Http\Message\ResponseInterface::class);
      });

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturnCallback(function (string $path) use ($destination_dir) {
      // The configured geoip_path ('/geoip') resolves to our real temp
      // directory; a temp file's own path is already real, so realpath()
      // on it is just an identity operation here.
      return $path === '/geoip' ? $destination_dir : $path;
    });
    $file_system->method('tempnam')->willReturnCallback(fn () => $this->tempPath('_download.gz'));

    $service = $this->downloadService($http_client, $file_system);

    $this->assertTrue($service->download());
    $this->assertSame($original, file_get_contents($destination));
  }

  /**
   * A download that never succeeds (every candidate month fails) reports failure.
   *
   * Never throws: this runs from cron and module-install contexts that must
   * not fail over a network hiccup, so the only observable outcome is the
   * FALSE return value (and a logged warning, not asserted here).
   */
  public function testFailedDownloadReturnsFalseRatherThanThrowing(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->method('request')->willThrowException(
      new \GuzzleHttp\Exception\ConnectException('could not connect', new \GuzzleHttp\Psr7\Request('GET', 'https://download.db-ip.com/'))
    );

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturn('/geoip');
    $file_system->method('tempnam')->willReturnCallback(fn () => $this->tempPath('_download.gz'));

    $service = $this->downloadService($http_client, $file_system);

    $this->assertFalse($service->download());
  }

}

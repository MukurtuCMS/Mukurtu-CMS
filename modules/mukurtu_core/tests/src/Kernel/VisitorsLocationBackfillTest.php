<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mukurtu_core\Traits\VisitorsTableTestTrait;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService;
use Drupal\mukurtu_core\Service\VisitorsLocationBackfill;
use Drupal\visitors_geoip\Service\RebuildLocationService;
use Drupal\visitors_geoip\VisitorsGeoIpInterface;
use GeoIp2\Model\City;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests geolocating visits saved before a geolocation database existed.
 *
 * Runs against the real visitors table and visitors_geoip's real
 * RebuildLocationService; only the geolocation database itself is a stub,
 * answering for two public addresses in the DB-IP City Lite record shape.
 *
 * @see \Drupal\mukurtu_core\Service\VisitorsLocationBackfill
 */
#[Group('mukurtu_core')]
class VisitorsLocationBackfillTest extends KernelTestBase {

  use VisitorsTableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'path', 'field', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installVisitors();
  }

  /**
   * Builds the backfill around a stub database that knows two addresses.
   *
   * @param bool $has_database
   *   Whether the stub reports a geolocation database as installed.
   * @param bool $small_batches
   *   Read two rows per batch, so a test can see it stop part way.
   */
  private function backfill(bool $has_database = TRUE, bool $small_batches = FALSE): VisitorsLocationBackfill {
    $records = [
      '8.8.8.8' => [
        'continent' => ['code' => 'NA'],
        'country' => ['iso_code' => 'US'],
        'subdivisions' => [['names' => ['en' => 'California']]],
        'city' => ['names' => ['en' => 'Mountain View']],
        'location' => ['latitude' => 37.422, 'longitude' => -122.085],
      ],
      '81.2.69.142' => [
        'continent' => ['code' => 'EU'],
        'country' => ['iso_code' => 'GB'],
        'subdivisions' => [['names' => ['en' => 'England']]],
        'city' => ['names' => ['en' => 'London']],
        'location' => ['latitude' => 51.5143, 'longitude' => -0.0912],
      ],
    ];
    $inner = $this->createMock(VisitorsGeoIpInterface::class);
    $inner->method('city')->willReturnCallback(fn($ip) => isset($records[$ip]) ? new City($records[$ip]) : NULL);
    $metadata = (object) ['databaseType' => 'DBIP-City-Lite', 'buildEpoch' => 0];
    $inner->method('metadata')->willReturn($has_database ? $metadata : NULL);
    $locator = $this->createMock(DbIpDatabaseLocator::class);
    $locator->method('realpath')->willReturn(NULL);
    $lookup = new DbIpFallbackGeoIpService($inner, $locator);

    $arguments = [
      $this->container->get('database'),
      $this->container->get('state'),
      $this->container->get('datetime.time'),
      new RebuildLocationService($this->container->get('database'), $lookup),
      $lookup,
    ];
    if (!$small_batches) {
      return new VisitorsLocationBackfill(...$arguments);
    }
    return new class(...$arguments) extends VisitorsLocationBackfill {

      /**
       * {@inheritdoc}
       */
      protected const BATCH_SIZE = 2;

    };
  }

  /**
   * Reads one visit's location columns.
   */
  private function location(int $id): array {
    return $this->container->get('database')
      ->query('SELECT location_country, location_region, location_city, location_latitude FROM {visitors} WHERE visitors_id = :id', [':id' => $id])
      ->fetchAssoc();
  }

  /**
   * Saved visits get their location, unknown addresses do not stall it.
   */
  public function testBackfillGeolocatesSavedVisits(): void {
    // As saved while the null-region bug was dropping geolocated visits: a
    // browser-language country and nothing else.
    $google = $this->insertVisit(['visitors_ip' => '8.8.8.8', 'location_country' => 'FR']);
    $private = $this->insertVisit(['visitors_ip' => '10.0.0.1', 'location_country' => 'US']);
    $google_again = $this->insertVisit(['visitors_ip' => '8.8.8.8']);
    $london = $this->insertVisit(['visitors_ip' => '81.2.69.142']);

    $backfill = $this->backfill();
    $this->assertTrue($backfill->startIfReady());
    // Three distinct addresses: the duplicate 8.8.8.8 counts once.
    $this->assertSame(3, $backfill->remaining());
    $backfill->process();

    $this->assertSame(['US', 'California', 'Mountain View'], array_slice(array_values($this->location($google)), 0, 3));
    $this->assertSame('California', $this->location($google_again)['location_region']);
    $this->assertSame('London', $this->location($london)['location_city']);
    // No database answers for a private address; it keeps its guess.
    $this->assertSame('US', $this->location($private)['location_country']);
    $this->assertNull($this->location($private)['location_latitude']);

    $this->assertSame('done', $this->container->get('state')->get(VisitorsLocationBackfill::STATE_KEY));
    $this->assertNull($backfill->remaining());
  }

  /**
   * Visits recorded after the backfill started are outside it.
   *
   * Those are geolocated as they are tracked.
   */
  public function testBackfillStopsAtTheHighestIdWhenItStarted(): void {
    $this->insertVisit(['visitors_ip' => '8.8.8.8']);
    $backfill = $this->backfill();
    $backfill->startIfReady();
    $later = $this->insertVisit(['visitors_ip' => '81.2.69.142']);

    $backfill->process();

    $this->assertNull($this->location($later)['location_latitude']);
  }

  /**
   * Out of time, it stops after a batch and picks up where it left off.
   */
  public function testBackfillResumesFromItsCursor(): void {
    $first = $this->insertVisit(['visitors_ip' => '8.8.8.8']);
    $this->insertVisit(['visitors_ip' => '10.0.0.1']);
    $third = $this->insertVisit(['visitors_ip' => '81.2.69.142']);

    $backfill = $this->backfill(TRUE, TRUE);
    $backfill->startIfReady();

    // No time at all still allows one batch, of two rows here.
    $backfill->process(0);
    $this->assertNotNull($this->location($first)['location_latitude']);
    $this->assertNull($this->location($third)['location_latitude']);
    $this->assertSame(1, $backfill->remaining());
    $this->assertSame($first + 1, $this->container->get('state')->get(VisitorsLocationBackfill::STATE_KEY)['cursor']);

    $backfill->process(0);
    $this->assertNotNull($this->location($third)['location_latitude']);
  }

  /**
   * Nothing starts without a database, and it only ever starts once.
   */
  public function testBackfillStartsOnceAndOnlyWithDatabase(): void {
    $this->insertVisit(['visitors_ip' => '8.8.8.8']);

    $this->assertFalse($this->backfill(FALSE)->startIfReady());
    $this->assertNull($this->container->get('state')->get(VisitorsLocationBackfill::STATE_KEY));

    $this->assertTrue($this->backfill()->startIfReady());
    $this->assertFalse($this->backfill()->startIfReady());
  }

  /**
   * An empty visitors table has nothing to do and is finished at once.
   */
  public function testEmptyTableIsDoneImmediately(): void {
    $backfill = $this->backfill();

    $this->assertTrue($backfill->startIfReady());
    $this->assertSame('done', $this->container->get('state')->get(VisitorsLocationBackfill::STATE_KEY));
    $this->assertNull($backfill->remaining());
  }

}

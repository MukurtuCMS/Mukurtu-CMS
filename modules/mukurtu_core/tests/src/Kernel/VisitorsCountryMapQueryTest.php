<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mukurtu_core\Traits\VisitorsTableTestTrait;
use Drupal\leaflet\LeafletService;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the visitor map's per-city query against the real visitors table.
 *
 * The rendering side needs Leaflet and a theme, and is covered by the unit
 * tests of buildFeatures() and aggregate(); this covers what they cannot:
 * that the query groups, filters and scopes the way the report tables do.
 *
 * @see \Drupal\mukurtu_core\Service\VisitorsCountryMap::getCityTotals()
 */
#[Group('mukurtu_core')]
class VisitorsCountryMapQueryTest extends KernelTestBase {

  use VisitorsTableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'path', 'field', 'options'];

  /**
   * The start of the stub date range.
   */
  private const START = 1_700_000_000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installVisitors();

    $visit = fn(string $visitor, string $country, string $region, string $city, float $lat, array $extra = []) => $this->insertVisit($extra + [
      'visitor_id' => $visitor,
      'visitors_date_time' => self::START + 100,
      'location_country' => $country,
      'location_region' => $region,
      'location_city' => $city,
      'location_latitude' => $lat,
      'location_longitude' => -$lat,
    ]);

    // Two visitors in Mountain View, one of them twice, at two positions.
    $visit('a', 'US', 'California', 'Mountain View', 37.0);
    $visit('a', 'US', 'California', 'Mountain View', 38.0);
    $visit('b', 'US', 'California', 'Mountain View', 38.0);
    $visit('c', 'US', 'Oregon', 'Portland', 45.5);
    $visit('d', 'US', '', '', 40.0);
    $visit('e', 'GB', 'England', 'London', 51.5);
    // Each of these is excluded by one of the report's own filters.
    $visit('bot', 'US', 'California', 'Mountain View', 37.0, ['bot' => 1]);
    $visit('old', 'US', 'California', 'Mountain View', 37.0, ['visitors_date_time' => self::START - 100]);
    $visit('nocoords', 'US', 'California', 'Mountain View', 0.0, ['location_latitude' => NULL]);
    $visit('nocountry', '', '', '', 1.0);
  }

  /**
   * Runs the protected query with a fixed date range.
   */
  private function cityTotals(?string $country = NULL, ?string $region = NULL): array {
    $date_range = new class(self::START) {

      public function __construct(private int $start) {}

      /**
       * The start of the range.
       */
      public function getStartTimestamp(): int {
        return $this->start;
      }

      /**
       * The end of the range.
       */
      public function getEndTimestamp(): int {
        return $this->start + 1000;
      }

    };
    $map = new VisitorsCountryMap(
      $this->container->get('database'),
      $this->createMock(LeafletService::class),
      $this->container->get('string_translation'),
      $this->container->get('config.factory'),
      $this->createMock(DbIpDatabaseLocator::class),
      $date_range,
      new \stdClass(),
    );

    $rows = (new \ReflectionMethod($map, 'getCityTotals'))->invoke($map, $country, $region);
    $out = [];
    foreach ($rows as $row) {
      $key = $row['country'] . '/' . $row['region'] . '/' . $row['city'];
      $out[$key] = [(int) $row['unique_visitors'], round((float) $row['lat'], 4)];
    }
    ksort($out);
    return $out;
  }

  /**
   * One row per city, counting distinct visitors, inside the report filters.
   */
  public function testCitiesAreGroupedWithinTheReportFilters(): void {
    $this->assertSame([
      'GB/England/London' => [1, 51.5],
      'US//' => [1, 40.0],
      'US/California/Mountain View' => [2, round((37.0 + 38.0 + 38.0) / 3, 4)],
      'US/Oregon/Portland' => [1, 45.5],
    ], $this->cityTotals());
  }

  /**
   * The country drill-down sees only its own country.
   */
  public function testCountryScope(): void {
    $this->assertSame(['GB/England/London'], array_keys($this->cityTotals('GB')));
  }

  /**
   * The region drill-down sees only its own region; '_none' means no region.
   */
  public function testRegionScope(): void {
    $this->assertSame(['US/Oregon/Portland'], array_keys($this->cityTotals('US', 'Oregon')));
    $this->assertSame(['US//'], array_keys($this->cityTotals('US', '_none')));
  }

}

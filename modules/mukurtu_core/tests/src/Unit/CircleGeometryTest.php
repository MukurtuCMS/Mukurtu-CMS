<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\mukurtu_core\CircleGeometry;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the circle to polygon conversion behind the accessible editor (#2164).
 *
 * Pure geometry, so this is a unit test with no Drupal bootstrap.
 */
#[Group('mukurtu_core')]
class CircleGeometryTest extends UnitTestCase {

  /**
   * Metres per degree of latitude, near enough for assertions at this scale.
   */
  private const METRES_PER_DEGREE_LAT = 111320.0;

  /**
   * The ring has the expected shape: 128 sides, closed.
   */
  public function testRingIsClosedAndHasTheExpectedSideCount(): void {
    $coordinates = CircleGeometry::toPolygonCoordinates(45.0, -100.0, 1000.0);

    $this->assertCount(1, $coordinates, 'A circle should produce a single ring with no holes.');
    $ring = $coordinates[0];

    // 128 distinct points plus the repeated closing point.
    $this->assertCount(CircleGeometry::SIDES + 1, $ring);
    $this->assertSame($ring[0], $ring[count($ring) - 1], 'The ring does not close, so it is not valid GeoJSON.');
  }

  /**
   * Every point sits the requested distance from the centre.
   *
   * This is the assertion that actually says "this is a circle". A ring of the
   * right length made of arbitrary points would pass the test above.
   */
  public function testEveryPointIsTheRadiusFromTheCentre(): void {
    $lat = 45.0;
    $lon = -100.0;
    $radius = 5000.0;

    $ring = CircleGeometry::toPolygonCoordinates($lat, $lon, $radius)[0];

    foreach ($ring as $point) {
      [$pointLon, $pointLat] = $point;
      $distance = $this->haversine($lat, $lon, $pointLat, $pointLon);
      // Within a metre, which is well inside the rounding applied to the
      // stored coordinates.
      $this->assertEqualsWithDelta($radius, $distance, 1.0, 'A ring point is not the requested distance from the centre.');
    }
  }

  /**
   * Longitude spacing widens with latitude, as it must on a sphere.
   *
   * A naive implementation that offsets degrees instead of metres would make
   * the circle the same width in degrees everywhere, which is an ellipse on
   * the ground at any latitude away from the equator.
   */
  public function testCirclesWidenInLongitudeTowardsThePoles(): void {
    $spread = static function (float $lat): float {
      $ring = CircleGeometry::toPolygonCoordinates($lat, 0.0, 10000.0)[0];
      $lons = array_column($ring, 0);
      return max($lons) - min($lons);
    };

    $this->assertGreaterThan(
      $spread(0.0) * 1.5,
      $spread(60.0),
      'A circle at 60 degrees latitude should span far more longitude than the same circle at the equator.'
    );
  }

  /**
   * Longitudes stay in range when a circle crosses the antimeridian.
   */
  public function testLongitudesStayInRangeAcrossTheAntimeridian(): void {
    $ring = CircleGeometry::toPolygonCoordinates(0.0, 179.995, 2000.0)[0];

    foreach ($ring as [$pointLon, $pointLat]) {
      $this->assertGreaterThanOrEqual(-180.0, $pointLon);
      $this->assertLessThanOrEqual(180.0, $pointLon);
    }
  }

  /**
   * A circle feature is recognised and its centre read in Leaflet's order.
   */
  public function testReadsCircleMetadataFromAFeature(): void {
    $circle = CircleGeometry::fromFeature([
      'type' => 'Feature',
      'properties' => ['circle_center' => [45.5, -122.6], 'circle_radius' => 750],
      'geometry' => ['type' => 'Polygon', 'coordinates' => []],
    ]);

    $this->assertNotNull($circle);
    // circle_center is [lat, lng], Leaflet's order, not GeoJSON's.
    $this->assertSame(45.5, $circle['lat']);
    $this->assertSame(-122.6, $circle['lon']);
    $this->assertSame(750.0, $circle['radius']);
  }

  /**
   * Features that are not usable circles are rejected.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('nonCircleProvider')]
  public function testRejectsNonCircles(array $feature, string $why): void {
    $this->assertNull(CircleGeometry::fromFeature($feature), $why);
  }

  public static function nonCircleProvider(): \Generator {
    yield 'no properties' => [
      ['type' => 'Feature'],
      'A feature with no properties is not a circle.',
    ];
    yield 'ordinary polygon' => [
      ['type' => 'Feature', 'properties' => ['location_description' => 'A place']],
      'A polygon with no circle metadata is not a circle.',
    ];
    yield 'radius without centre' => [
      ['type' => 'Feature', 'properties' => ['circle_radius' => 500]],
      'A radius alone does not describe a circle.',
    ];
    yield 'zero radius' => [
      ['type' => 'Feature', 'properties' => ['circle_center' => [1, 2], 'circle_radius' => 0]],
      'A zero radius is not an editable circle.',
    ];
    yield 'non numeric radius' => [
      ['type' => 'Feature', 'properties' => ['circle_center' => [1, 2], 'circle_radius' => 'wide']],
      'A non numeric radius is unusable.',
    ];
  }

  /**
   * Writing a circle back produces both the metadata and a matching polygon.
   */
  public function testApplyingACircleWritesMetadataAndGeometry(): void {
    $feature = CircleGeometry::applyToFeature(
      ['type' => 'Feature', 'properties' => ['location_description' => 'Kept']],
      10.0,
      20.0,
      1500.0
    );

    $this->assertSame([10.0, 20.0], $feature['properties']['circle_center']);
    $this->assertSame(1500.0, $feature['properties']['circle_radius']);
    $this->assertSame('Kept', $feature['properties']['location_description'], 'Unrelated properties were dropped.');
    $this->assertSame('Polygon', $feature['geometry']['type']);
    $this->assertCount(CircleGeometry::SIDES + 1, $feature['geometry']['coordinates'][0]);
  }

  /**
   * A circle survives a write then read cycle unchanged.
   */
  public function testRoundTrip(): void {
    $feature = CircleGeometry::applyToFeature(['type' => 'Feature', 'properties' => []], -33.87, 151.21, 2500.0);
    $circle = CircleGeometry::fromFeature($feature);

    $this->assertNotNull($circle);
    $this->assertEqualsWithDelta(-33.87, $circle['lat'], 0.0000001);
    $this->assertEqualsWithDelta(151.21, $circle['lon'], 0.0000001);
    $this->assertSame(2500.0, $circle['radius']);
  }

  /**
   * Great-circle distance in metres, for asserting the ring really is round.
   */
  private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earth = 6371008.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
      + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
  }

}

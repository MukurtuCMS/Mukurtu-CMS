<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Tests\UnitTestCase;
use Drupal\leaflet\LeafletService;
use Drupal\mukurtu_core\Service\DbIpDatabaseLocator;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the map points to Leaflet features transformation, and roll-up.
 *
 * Only buildFeatures() and aggregate() are covered here. The query around
 * them is covered by VisitorsCountryMapQueryTest, and the render side needs
 * Leaflet and a theme, which a unit test cannot supply.
 *
 * @see \Drupal\mukurtu_core\Service\VisitorsCountryMap::buildFeatures()
 */
#[Group('mukurtu_core')]
class VisitorsCountryMapFeaturesTest extends UnitTestCase {

  /**
   * Builds the service with the collaborators buildFeatures() actually uses.
   */
  private function mapService(): VisitorsCountryMap {
    // An anonymous stand-in rather than a mock: the real collaborator is
    // \Drupal\visitors\Service\LocationService, whose class is only
    // autoloadable when the visitors module is enabled, and the service takes
    // it untyped for exactly that reason. (Also avoids MockBuilder's
    // addMethods(), deprecated in PHPUnit 11 and gone in 12.)
    $location = new class {

      /**
       * Returns a country name for a country code.
       */
      public function getCountryLabel(string $code): string {
        return ['US' => 'United States', 'FJ' => 'Fiji', 'CA' => 'Canada'][$code] ?? $code;
      }

    };

    return new VisitorsCountryMap(
      $this->createMock(Connection::class),
      $this->createMock(LeafletService::class),
      $this->getStringTranslationStub(),
      $this->getConfigFactoryStub([VisitorsCountryMap::CONFIG_NAME => ['city_threshold' => 3]]),
      $this->createMock(DbIpDatabaseLocator::class),
      NULL,
      $location,
    );
  }

  /**
   * A country row becomes one point feature carrying a labelled tooltip.
   */
  public function testRowBecomesAPointFeature(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => '4', 'lat' => '39.8', 'lng' => '-98.6'],
    ]);

    $this->assertCount(1, $features);
    $this->assertSame('point', $features[0]['type']);
    $this->assertSame(39.8, $features[0]['lat']);
    $this->assertSame(-98.6, $features[0]['lon']);
    $this->assertStringContainsString('United States', (string) $features[0]['tooltip']['value']);
    $this->assertStringContainsString('4', (string) $features[0]['tooltip']['value']);
  }

  /**
   * No popup is produced.
   *
   * Popups put their close button several tab stops away behind every other
   * marker, and Escape does not reach them from the marker that opened one.
   * The tooltip already shows on focus and carries the same text.
   */
  public function testNoPopupIsProduced(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => '4', 'lat' => '39.8', 'lng' => '-98.6'],
    ]);

    $this->assertArrayNotHasKey('popup', $features[0]);
    $this->assertArrayHasKey('tooltip', $features[0]);
  }

  /**
   * A single visitor reads as singular.
   */
  public function testSingleVisitorUsesTheSingularForm(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'FJ', 'unique_visitors' => '1', 'lat' => '-17.7', 'lng' => '178.0'],
    ]);

    $text = (string) $features[0]['tooltip']['value'];
    $this->assertStringContainsString('1 unique visitor', $text);
    $this->assertStringNotContainsString('unique visitors', $text);
  }

  /**
   * Rows without usable coordinates are skipped rather than pinned at 0,0.
   *
   * The query filters these out, but a NULL here would otherwise cast to 0.0
   * and drop a marker in the Gulf of Guinea.
   */
  public function testRowsWithoutCoordinatesAreSkipped(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => '4', 'lat' => NULL, 'lng' => '-98.6'],
      ['country' => 'FJ', 'unique_visitors' => '1', 'lat' => '-17.7', 'lng' => NULL],
      ['country' => 'CA', 'unique_visitors' => '2', 'lat' => '56.1', 'lng' => '-106.3'],
    ]);

    $this->assertCount(1, $features);
    $this->assertSame(56.1, $features[0]['lat']);
  }

  /**
   * No rows means no features, which is what triggers the empty state.
   */
  public function testNoRowsMeansNoFeatures(): void {
    $this->assertSame([], $this->mapService()->buildFeatures([]));
  }

  /**
   * Coordinates arrive from the database as strings and must become floats.
   */
  public function testCoordinatesAreCastToFloats(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => '4', 'lat' => '39.8', 'lng' => '-98.6'],
    ]);

    $this->assertIsFloat($features[0]['lat']);
    $this->assertIsFloat($features[0]['lon']);
  }

  /**
   * A city point is labelled "City, Region, Country".
   */
  public function testCityPointIsLabelledWithItsPlace(): void {
    $features = $this->mapService()->buildFeatures([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'Mountain View',
        'other' => FALSE,
        'unique_visitors' => 5,
        'lat' => 37.4,
        'lng' => -122.1,
      ],
    ]);

    $this->assertStringContainsString('Mountain View, California, United States: 5 unique visitors', (string) $features[0]['tooltip']['value']);
  }

  /**
   * A pooled point is labelled with its region, or its country at top level.
   */
  public function testPooledPointsAreLabelledAsOtherCities(): void {
    $features = $this->mapService()->buildFeatures([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => '',
        'other' => TRUE,
        'unique_visitors' => 4,
        'lat' => 37.0,
        'lng' => -120.0,
      ],
      [
        'country' => 'CA',
        'region' => '',
        'city' => '',
        'other' => TRUE,
        'unique_visitors' => 3,
        'lat' => 50.0,
        'lng' => -100.0,
      ],
    ]);

    $this->assertStringContainsString('Other cities in California', (string) $features[0]['tooltip']['value']);
    $this->assertStringContainsString('Other cities in Canada', (string) $features[1]['tooltip']['value']);
  }

  /**
   * The marker is a focusable HTML marker whose hidden text names it.
   *
   * The 'circle_marker' icon type of the leaflet module would draw an SVG
   * path that Leaflet does not make focusable, leaving keyboard users no way
   * to the tooltip.
   */
  public function testMarkerIsAnHtmlMarkerNamedByItsText(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => '4', 'lat' => '39.8', 'lng' => '-98.6'],
    ]);

    $icon = $features[0]['icon'];
    $this->assertSame('html', $icon['iconType']);
    $this->assertStringContainsString('<span class="visually-hidden">United States: 4 unique visitors</span>', $icon['html']);
  }

  /**
   * Place names from the geolocation database are escaped in the marker.
   *
   * Both the tooltip and the marker HTML are inserted as markup by Leaflet.
   */
  public function testPlaceNamesAreEscaped(): void {
    $features = $this->mapService()->buildFeatures([
      [
        'country' => 'US',
        'region' => '<b>R</b>',
        'city' => '<img src=x onerror=alert(1)>',
        'other' => FALSE,
        'unique_visitors' => 3,
        'lat' => 1.0,
        'lng' => 1.0,
      ],
      [
        'country' => 'US',
        'region' => "Côte d'Or",
        'city' => '',
        'other' => TRUE,
        'unique_visitors' => 3,
        'lat' => 1.0,
        'lng' => 1.0,
      ],
    ]);

    $this->assertStringNotContainsString('<img', $features[0]['icon']['html']);
    $this->assertStringNotContainsString('<b>', (string) $features[0]['tooltip']['value']);
    $this->assertStringContainsString('&lt;img', $features[0]['icon']['html']);
    // Escaped once, not twice: the pooled label passes through two t() calls.
    $this->assertStringContainsString('Côte d&#039;Or', (string) $features[1]['tooltip']['value']);
    $this->assertStringNotContainsString('&amp;#039;', (string) $features[1]['tooltip']['value']);
  }

  /**
   * Circle area tracks the count, from the 24px target size minimum up.
   */
  public function testMarkerSizeScalesWithTheCount(): void {
    $features = $this->mapService()->buildFeatures([
      ['country' => 'US', 'unique_visitors' => 100, 'lat' => 1.0, 'lng' => 1.0],
      ['country' => 'CA', 'unique_visitors' => 25, 'lat' => 2.0, 'lng' => 2.0],
      ['country' => 'FJ', 'unique_visitors' => 0, 'lat' => 3.0, 'lng' => 3.0],
    ]);

    $this->assertSame(['x' => 48, 'y' => 48], $features[0]['icon']['iconSize']);
    // A quarter of the largest count gets half its extra diameter.
    $this->assertSame(['x' => 36, 'y' => 36], $features[1]['icon']['iconSize']);
    $this->assertSame(['x' => 24, 'y' => 24], $features[2]['icon']['iconSize']);
  }

  /**
   * Cities at or over the threshold keep their own point.
   */
  public function testCitiesAtTheThresholdArePlottedOnTheirOwn(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'Mountain View',
        'unique_visitors' => '3',
        'lat' => '37.4',
        'lng' => '-122.1',
      ],
    ], 3);

    $this->assertCount(1, $points);
    $this->assertFalse($points[0]['other']);
    $this->assertSame('Mountain View', $points[0]['city']);
    $this->assertSame(37.4, $points[0]['lat']);
  }

  /**
   * Small cities pool into their region, at the visit-weighted centre.
   */
  public function testSmallCitiesPoolIntoTheirRegion(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'A',
        'unique_visitors' => 2,
        'lat' => 10.0,
        'lng' => 20.0,
      ],
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'B',
        'unique_visitors' => 1,
        'lat' => 40.0,
        'lng' => 50.0,
      ],
    ], 3);

    $this->assertCount(1, $points);
    $this->assertTrue($points[0]['other']);
    $this->assertSame('California', $points[0]['region']);
    $this->assertSame('', $points[0]['city']);
    $this->assertSame(3, $points[0]['unique_visitors']);
    $this->assertEqualsWithDelta(20.0, $points[0]['lat'], 0.0001);
    $this->assertEqualsWithDelta(30.0, $points[0]['lng'], 0.0001);
  }

  /**
   * Regions still under the threshold pool into their country.
   */
  public function testSmallRegionsPoolIntoTheirCountry(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'A',
        'unique_visitors' => 1,
        'lat' => 10.0,
        'lng' => 10.0,
      ],
      ['country' => 'US', 'region' => 'Oregon', 'city' => 'B', 'unique_visitors' => 1, 'lat' => 20.0, 'lng' => 20.0],
      ['country' => 'US', 'region' => '', 'city' => '', 'unique_visitors' => 1, 'lat' => 30.0, 'lng' => 30.0],
    ], 3);

    $this->assertCount(1, $points);
    $this->assertTrue($points[0]['other']);
    $this->assertSame('US', $points[0]['country']);
    $this->assertSame('', $points[0]['region']);
    $this->assertSame(3, $points[0]['unique_visitors']);
    $this->assertEqualsWithDelta(20.0, $points[0]['lat'], 0.0001);
  }

  /**
   * A lone visitor is never plotted: a country still under it is dropped.
   *
   * Its average position would otherwise be that visitor's own city.
   */
  public function testCountriesUnderTheThresholdAreLeftOff(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'FJ',
        'region' => 'Central',
        'city' => 'Suva',
        'unique_visitors' => 1,
        'lat' => -18.1,
        'lng' => 178.4,
      ],
      [
        'country' => 'US',
        'region' => 'California',
        'city' => 'Mountain View',
        'unique_visitors' => 5,
        'lat' => 37.4,
        'lng' => -122.1,
      ],
    ], 3);

    $this->assertCount(1, $points);
    $this->assertSame('US', $points[0]['country']);
  }

  /**
   * A city with no name is never a point of its own, whatever its count.
   */
  public function testUnnamedCitiesAlwaysPool(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'US',
        'region' => 'California',
        'city' => '',
        'unique_visitors' => 9,
        'lat' => 37.0,
        'lng' => -120.0,
      ],
    ], 3);

    $this->assertCount(1, $points);
    $this->assertTrue($points[0]['other']);
    $this->assertSame('California', $points[0]['region']);
  }

  /**
   * A threshold of 1 plots every city.
   */
  public function testThresholdOfOnePlotsEveryCity(): void {
    $points = VisitorsCountryMap::aggregate([
      [
        'country' => 'FJ',
        'region' => 'Central',
        'city' => 'Suva',
        'unique_visitors' => 1,
        'lat' => -18.1,
        'lng' => 178.4,
      ],
    ], 1);

    $this->assertCount(1, $points);
    $this->assertFalse($points[0]['other']);
  }

}

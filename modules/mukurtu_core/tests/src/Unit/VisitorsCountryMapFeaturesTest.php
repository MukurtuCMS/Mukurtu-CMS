<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Tests\UnitTestCase;
use Drupal\leaflet\LeafletService;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the country rows to Leaflet features transformation.
 *
 * Only buildFeatures() is covered here. The query around it needs a populated
 * visitors table and the render side needs Leaflet, neither of which a unit
 * test can supply, and mukurtu_core is too heavy to enable in a Kernel test
 * (~30 module dependencies) for this alone.
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

}

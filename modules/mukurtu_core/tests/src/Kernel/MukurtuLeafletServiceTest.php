<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that leaflet.service restores circle_center/circle_radius on display.
 *
 * A drawn circle is stored as a polygon approximation carrying custom
 * circle_center/circle_radius GeoJSON Feature properties (see
 * GeofieldMukurtuCircleTest). Contrib's LeafletService::leafletProcessGeofield()
 * has no notion of those properties and would normally just emit a plain
 * polygon point list for view/browse pages to render, which is what made
 * circles look faceted on the front end. MukurtuLeafletService decorates
 * leaflet.service to restore them as a 'circle' typed feature instead.
 *
 * @see \Drupal\mukurtu_core\Service\MukurtuLeafletService
 */
#[Group('mukurtu_core')]
class MukurtuLeafletServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'text',
    'node',
    'mukurtu_core',
  ];

  /**
   * A circle's Feature properties are converted into a 'circle' datum.
   */
  public function testCircleFeatureBecomesCircleDatum(): void {
    $geojson = $this->buildFeatureCollection([
      $this->polygonFeature(['circle_center' => [46.7, -117.2], 'circle_radius' => 500]),
    ]);

    $data = \Drupal::service('leaflet.service')->leafletProcessGeofield($geojson);

    $this->assertCount(1, $data);
    $this->assertSame('circle', $data[0]['type']);
    $this->assertSame(46.7, $data[0]['lat']);
    $this->assertSame(-117.2, $data[0]['lon']);
    $this->assertSame(500.0, $data[0]['radius']);
    $this->assertTrue(
      $data[0]['markercluster_excluded'],
      'A circle must opt out of marker clustering: L.Circle has a getRadius() method, same as the circleMarker Drupal.Leaflet treats as a clusterable point, so without this flag it gets handed to L.MarkerClusterGroup.addLayer() (which requires a real L.Marker) and throws.'
    );
  }

  /**
   * A polygon with no circle properties still renders as a plain polygon.
   *
   * Regression guard: the decorator must not alter geometry it isn't
   * upgrading to a circle.
   */
  public function testPlainPolygonPassesThroughUnchanged(): void {
    $geojson = $this->buildFeatureCollection([$this->polygonFeature([])]);

    $data = \Drupal::service('leaflet.service')->leafletProcessGeofield($geojson);

    $this->assertCount(1, $data);
    $this->assertSame('polygon', $data[0]['type']);
  }

  /**
   * A node can bundle a marker point and a circle in one stored value.
   *
   * The widget's "drawn items" group serializes as one FeatureCollection
   * per field value, so a plain point and a circle can legitimately arrive
   * together in a single call. Only the circle feature should convert; the
   * point must render unchanged, not be dropped or merged away.
   */
  public function testMixedPointAndCircleCollectionKeepsBothShapes(): void {
    $geojson = $this->buildFeatureCollection([
      [
        'type' => 'Feature',
        'properties' => [],
        'geometry' => ['type' => 'Point', 'coordinates' => [-117.4, 46.9]],
      ],
      $this->polygonFeature(['circle_center' => [46.7, -117.2], 'circle_radius' => 500]),
    ]);

    $data = \Drupal::service('leaflet.service')->leafletProcessGeofield($geojson);

    $this->assertCount(2, $data);
    $this->assertSame('point', $data[0]['type']);
    $this->assertSame(46.9, $data[0]['lat']);
    $this->assertSame(-117.4, $data[0]['lon']);

    $this->assertSame('circle', $data[1]['type']);
    $this->assertSame(46.7, $data[1]['lat']);
    $this->assertSame(-117.2, $data[1]['lon']);
    $this->assertSame(500.0, $data[1]['radius']);
    $this->assertTrue($data[1]['markercluster_excluded']);

    // The point must not be swept into the same "exclude from clustering"
    // treatment as the circle sitting next to it in the same collection.
    $this->assertArrayNotHasKey('markercluster_excluded', $data[0]);
  }

  /**
   * Builds a GeoJSON FeatureCollection string from a list of features.
   */
  private function buildFeatureCollection(array $features): string {
    return json_encode([
      'type' => 'FeatureCollection',
      'features' => $features,
    ]);
  }

  /**
   * Builds a small triangular Polygon Feature, as circleToPolygon would.
   */
  private function polygonFeature(array $properties): array {
    return [
      'type' => 'Feature',
      'properties' => $properties,
      'geometry' => [
        'type' => 'Polygon',
        'coordinates' => [[[-117.2, 46.7], [-117.19, 46.7], [-117.2, 46.71], [-117.2, 46.7]]],
      ],
    ];
  }

}

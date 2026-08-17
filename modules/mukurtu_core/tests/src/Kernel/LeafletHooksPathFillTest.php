<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\LeafletHooks;

/**
 * Tests removal of path fill for line geometries on Leaflet maps.
 *
 * @see \Drupal\mukurtu_core\Hook\LeafletHooks
 * @group mukurtu_core
 */
class LeafletHooksPathFillTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'geofield',
    'leaflet',
    'mukurtu_core',
  ];

  /**
   * Tests that fill is disabled only for line-type features.
   *
   * @dataProvider featureProvider
   */
  public function testFillRemovedForLinesOnly(string $type, ?string $path, ?bool $expectedFill): void {
    $feature = ['type' => $type];
    if ($path !== NULL) {
      $feature['path'] = $path;
    }

    (new LeafletHooks())->leafletFormatterFeatureAlter($feature, NULL, NULL);

    if ($expectedFill === NULL) {
      $this->assertSame($path, $feature['path'] ?? NULL);
      return;
    }

    $decoded = json_decode($feature['path'], TRUE);
    $this->assertSame($expectedFill, $decoded['fill']);
    // Other configured style properties are preserved.
    $this->assertSame('#3388ff', $decoded['color']);
  }

  /**
   * Tests the Views variant behaves identically to the formatter variant.
   */
  public function testViewsVariantMatchesFormatterVariant(): void {
    $path = '{"color":"#3388ff","fill":"depends"}';
    $feature = ['type' => 'linestring', 'path' => $path];

    (new LeafletHooks())->leafletViewsFeatureAlter($feature, NULL, NULL);

    $decoded = json_decode($feature['path'], TRUE);
    $this->assertFalse($decoded['fill']);
  }

  /**
   * Tests that only line sub-components of a mixed collection lose fill.
   *
   * A geofield value with more than one line drawn into it (e.g. via the
   * Mukurtu map widget) is parsed by leaflet/LeafletService into a
   * 'geometrycollection' feature whose individual geometries are nested
   * under 'component', rather than a top-level 'linestring'/'multilinestring'
   * type. Mixing a polygon into the same collection must not lose its fill.
   *
   * The collection's own 'path' must end up blanked (not merely left as the
   * original "depends" value): Drupal.Leaflet.create_feature() re-applies a
   * feature's own path to the whole Leaflet layer it built, and for a
   * collection that layer is a FeatureGroup whose setStyle() cascades to
   * every child - so a non-empty collection-level path would silently
   * re-enable fill on the very components fixed here.
   */
  public function testGeometryCollectionOnlyDisablesFillOnLineComponents(): void {
    $path = '{"color":"#3388ff","fill":"depends"}';
    $feature = [
      'type' => 'geometrycollection',
      'path' => $path,
      'component' => [
        ['type' => 'linestring', 'points' => []],
        ['type' => 'polygon', 'points' => []],
      ],
    ];

    (new LeafletHooks())->leafletFormatterFeatureAlter($feature, NULL, NULL);

    // The collection's own path is blanked so the client-side cascading
    // restyle of the whole FeatureGroup becomes a no-op.
    $this->assertSame('', $feature['path']);

    // The linestring component gained its own fill-disabled path.
    $lineDecoded = json_decode($feature['component'][0]['path'], TRUE);
    $this->assertFalse($lineDecoded['fill']);
    $this->assertSame('#3388ff', $lineDecoded['color']);

    // The polygon component got its own explicit copy of the original path
    // (fill untouched), so it keeps rendering as configured once the
    // collection's own path is blanked.
    $polygonDecoded = json_decode($feature['component'][1]['path'], TRUE);
    $this->assertSame('depends', $polygonDecoded['fill']);
    $this->assertSame('#3388ff', $polygonDecoded['color']);
  }

  /**
   * Tests that fill-disabling recurses through nested collections.
   */
  public function testNestedGeometryCollectionRecursesIntoComponents(): void {
    $path = '{"color":"#3388ff","fill":"depends"}';
    $feature = [
      'type' => 'geometrycollection',
      'path' => $path,
      'component' => [
        [
          'type' => 'geometrycollection',
          'component' => [
            ['type' => 'linestring', 'points' => []],
          ],
        ],
      ],
    ];

    (new LeafletHooks())->leafletFormatterFeatureAlter($feature, NULL, NULL);

    // The nested collection's own path is blanked too, for the same reason
    // as the outer one.
    $this->assertSame('', $feature['component'][0]['path']);

    $nestedLine = $feature['component'][0]['component'][0];
    $decoded = json_decode($nestedLine['path'], TRUE);
    $this->assertFalse($decoded['fill']);
  }

  /**
   * Data provider of feature types and path settings.
   */
  public static function featureProvider(): array {
    $path = '{"color":"#3388ff","opacity":"1.0","stroke":true,"weight":3,"fill":"depends","fillColor":"*","fillOpacity":"0.2","radius":"6"}';

    return [
      'linestring loses fill' => ['linestring', $path, FALSE],
      'multilinestring loses fill' => ['multilinestring', $path, FALSE],
      'multipolyline loses fill' => ['multipolyline', $path, FALSE],
      'polygon keeps configured fill' => ['polygon', $path, NULL],
      'multipolygon keeps configured fill' => ['multipolygon', $path, NULL],
      'point is left untouched' => ['point', $path, NULL],
      'point with no path key is left untouched' => ['point', NULL, NULL],
      'linestring with malformed path JSON is left untouched' => ['linestring', 'not-json', NULL],
      'linestring with missing path is left untouched' => ['linestring', NULL, NULL],
    ];
  }

}

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
   * Data provider of feature types and path settings.
   */
  public static function featureProvider(): array {
    $path = '{"color":"#3388ff","opacity":"1.0","stroke":true,"weight":3,"fill":"depends","fillColor":"*","fillOpacity":"0.2","radius":"6"}';

    return [
      'linestring loses fill' => ['linestring', $path, FALSE],
      'multilinestring loses fill' => ['multilinestring', $path, FALSE],
      'polygon keeps configured fill' => ['polygon', $path, NULL],
      'multipolygon keeps configured fill' => ['multipolygon', $path, NULL],
      'point is left untouched' => ['point', $path, NULL],
      'point with no path key is left untouched' => ['point', NULL, NULL],
      'linestring with malformed path JSON is left untouched' => ['linestring', 'not-json', NULL],
      'linestring with missing path is left untouched' => ['linestring', NULL, NULL],
    ];
  }

}

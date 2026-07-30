<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Plugin\Field\FieldWidget\GeofieldMukurtuLatLonWidget;

/**
 * Tests GeofieldMukurtuLatLonWidget's GeoJSON FeatureCollection round trip.
 *
 * field_coverage stores a raw GeoJSON FeatureCollection (not WKT), where
 * each Feature may carry a properties.location_description read by
 * MukurtuLeafletFormatter. These tests guard the parsing/save logic that
 * has to reproduce that exact shape while never touching geometry this
 * widget doesn't expose for editing (multi-part geometries, polygons with
 * holes, legacy WKT).
 *
 * @see \Drupal\mukurtu_core\Plugin\Field\FieldWidget\GeofieldMukurtuLatLonWidget
 * @group mukurtu_core
 */
class GeofieldMukurtuLatLonWidgetTest extends KernelTestBase {

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
    'mukurtu_core',
  ];

  /**
   * The widget under test.
   */
  protected GeofieldMukurtuLatLonWidget $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $fieldDefinition = BaseFieldDefinition::create('geofield')
      ->setName('field_coverage')
      ->setLabel('Map Points');

    $this->widget = \Drupal::service('plugin.manager.field.widget')->createInstance('geofield_mukurtu_latlon', [
      'field_definition' => $fieldDefinition,
      'settings' => [],
      'third_party_settings' => [],
    ]);
  }

  /**
   * Runs massageFormValues() against a fake submitted "value" sub-tree and
   * returns the resulting stored string.
   */
  protected function massage(array $submittedValue): string {
    $values = [0 => ['value' => $submittedValue]];
    $result = $this->widget->massageFormValues($values, [], new FormState());
    return $result[0]['value'];
  }

  /**
   * Invokes the protected parseStoredValue() method directly.
   */
  protected function parseStored(?string $value): array {
    $method = new \ReflectionMethod($this->widget, 'parseStoredValue');
    $method->setAccessible(TRUE);
    return $method->invoke($this->widget, $value);
  }

  /**
   * Builds a submitted Point shape row.
   */
  protected function pointShape(string $lat, string $lon, string $description = '', array $sourceFeature = ['type' => 'Feature', 'properties' => []], $sourceIndex = NULL): array {
    return [
      'shape_type' => 'Point',
      'coordinates' => ['lat' => $lat, 'lon' => $lon],
      'location_description' => $description,
      'source_feature' => $sourceFeature,
      'source_index' => $sourceIndex,
    ];
  }

  /**
   * Builds a submitted Line/Polygon shape row from a list of [lat, lon]
   * pairs.
   */
  protected function lineOrPolygonShape(string $type, array $latLonPairs, string $description = '', array $sourceFeature = ['type' => 'Feature', 'properties' => []], $sourceIndex = NULL): array {
    $vertices = [];
    foreach ($latLonPairs as $i => $pair) {
      [$lat, $lon] = $pair;
      $vertices[$i] = ['coordinates' => ['lat' => $lat, 'lon' => $lon]];
    }
    return [
      'shape_type' => $type,
      'vertices' => $vertices,
      'location_description' => $description,
      'source_feature' => $sourceFeature,
      'source_index' => $sourceIndex,
    ];
  }

  public function testSinglePointWithDescription(): void {
    $decoded = json_decode($this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->pointShape('46.7298', '-117.1817', 'Pullman, WA')],
    ]), TRUE);

    $this->assertSame('FeatureCollection', $decoded['type']);
    $this->assertCount(1, $decoded['features']);
    $this->assertSame('Point', $decoded['features'][0]['geometry']['type']);
    // GeoJSON coordinate order is [lon, lat].
    $this->assertSame([-117.1817, 46.7298], $decoded['features'][0]['geometry']['coordinates']);
    $this->assertSame('Pullman, WA', $decoded['features'][0]['properties']['location_description']);
  }

  public function testSinglePointWithoutDescriptionEncodesEmptyPropertiesObject(): void {
    $json = $this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->pointShape('46.7298', '-117.1817')],
    ]);

    // json_encode([]) would produce "[]", which is not a valid GeoJSON
    // properties object and diverges from what the map widget writes.
    $this->assertStringContainsString('"properties":{}', $json);
    $this->assertStringNotContainsString('"properties":[]', $json);
  }

  public function testMultiplePoints(): void {
    $decoded = json_decode($this->massage([
      'preserved_features' => [],
      'shapes' => [
        0 => $this->pointShape('1', '2', 'A'),
        1 => $this->pointShape('3', '4', 'B'),
      ],
    ]), TRUE);

    $this->assertCount(2, $decoded['features']);
    $this->assertSame('A', $decoded['features'][0]['properties']['location_description']);
    $this->assertSame('B', $decoded['features'][1]['properties']['location_description']);
  }

  public function testLineStringRoundTrip(): void {
    $decoded = json_decode($this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->lineOrPolygonShape('LineString', [
        ['46.0', '-117.0'],
        ['46.1', '-117.1'],
        ['46.2', '-117.2'],
      ], 'A trail')],
    ]), TRUE);

    $this->assertCount(1, $decoded['features']);
    $feature = $decoded['features'][0];
    $this->assertSame('LineString', $feature['geometry']['type']);
    // assertEquals, not assertSame: json_encode() drops the ".0" from
    // whole-number floats, so -117.0 round-trips through JSON as the
    // integer -117 - numerically identical, but a different PHP type.
    $this->assertEquals([
      [-117.0, 46.0],
      [-117.1, 46.1],
      [-117.2, 46.2],
    ], $feature['geometry']['coordinates']);
    $this->assertSame('A trail', $feature['properties']['location_description']);
  }

  public function testLineStringWithOnlyOneValidVertexIsDropped(): void {
    $value = $this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->lineOrPolygonShape('LineString', [
        ['46.0', '-117.0'],
        ['', ''],
      ])],
    ]);
    $this->assertSame('', $value);
  }

  public function testPolygonRoundTripClosesTheRing(): void {
    $decoded = json_decode($this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->lineOrPolygonShape('Polygon', [
        ['0', '0'],
        ['0', '1'],
        ['1', '1'],
      ], 'A field')],
    ]), TRUE);

    $this->assertCount(1, $decoded['features']);
    $feature = $decoded['features'][0];
    $this->assertSame('Polygon', $feature['geometry']['type']);
    $ring = $feature['geometry']['coordinates'][0];
    $this->assertCount(4, $ring);
    $this->assertSame($ring[0], $ring[3]);
    $this->assertEquals([0.0, 0.0], $ring[0]);
    $this->assertEquals([1.0, 0.0], $ring[1]);
    $this->assertEquals([1.0, 1.0], $ring[2]);
    $this->assertSame('A field', $feature['properties']['location_description']);
  }

  public function testPolygonWithOnlyTwoValidVerticesIsDropped(): void {
    $value = $this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->lineOrPolygonShape('Polygon', [
        ['0', '0'],
        ['0', '1'],
        ['', ''],
      ])],
    ]);
    $this->assertSame('', $value);
  }

  public function testNonPointFeaturesArePreservedByteIdenticalOnSave(): void {
    $polygonWithHole = [
      'type' => 'Feature',
      'properties' => ['foo' => 'bar'],
      'geometry' => [
        'type' => 'Polygon',
        'coordinates' => [
          [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
          [[1, 1], [2, 1], [2, 2], [1, 2], [1, 1]],
        ],
      ],
    ];

    $decoded = json_decode($this->massage([
      'preserved_features' => [0 => $polygonWithHole],
      'shapes' => [1 => $this->pointShape('10', '20', '', ['type' => 'Feature', 'properties' => []], 1)],
    ]), TRUE);

    $this->assertCount(2, $decoded['features']);
    $this->assertSame($polygonWithHole, $decoded['features'][0]);
    $this->assertSame('Point', $decoded['features'][1]['geometry']['type']);
  }

  public function testMixedFeatureCollectionRoundTripsEachShapeIndependently(): void {
    $stored = json_encode([
      'type' => 'FeatureCollection',
      'features' => [
        ['type' => 'Feature', 'properties' => ['location_description' => 'Camp'], 'geometry' => ['type' => 'Point', 'coordinates' => [-117.0, 46.0]]],
        ['type' => 'Feature', 'properties' => ['location_description' => 'Trail'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[-117.0, 46.0], [-117.1, 46.1]]]],
        ['type' => 'Feature', 'properties' => ['location_description' => 'Field'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 1], [1, 1], [0, 0]]]]],
      ],
    ]);

    $parsed = $this->parseStored($stored);
    $this->assertCount(3, $parsed['shapes']);
    $this->assertSame([], $parsed['other']);
    $this->assertNull($parsed['passthrough']);
    $this->assertSame('Point', $parsed['shapes'][0]['type']);
    $this->assertSame('LineString', $parsed['shapes'][1]['type']);
    $this->assertSame('Polygon', $parsed['shapes'][2]['type']);
    $this->assertCount(2, $parsed['shapes'][1]['vertices']);
    // The closing point is stripped for editing.
    $this->assertCount(3, $parsed['shapes'][2]['vertices']);

    // Edit only the point; the line and polygon should round-trip
    // untouched via their preserved source_feature/source_index.
    $submitted = [
      'preserved_features' => [],
      'shapes' => [
        0 => $this->pointShape('47.0', '-118.0', 'Camp', $parsed['shapes'][0]['feature'], $parsed['shapes'][0]['index']),
        1 => $this->lineOrPolygonShape('LineString', [['46.0', '-117.0'], ['46.1', '-117.1']], 'Trail', $parsed['shapes'][1]['feature'], $parsed['shapes'][1]['index']),
        2 => $this->lineOrPolygonShape('Polygon', [['0', '0'], ['1', '0'], ['1', '1']], 'Field', $parsed['shapes'][2]['feature'], $parsed['shapes'][2]['index']),
      ],
    ];
    $decoded = json_decode($this->massage($submitted), TRUE);
    $this->assertCount(3, $decoded['features']);
    $this->assertEquals([-118.0, 47.0], $decoded['features'][0]['geometry']['coordinates']);
    $this->assertSame('LineString', $decoded['features'][1]['geometry']['type']);
    $this->assertSame('Polygon', $decoded['features'][2]['geometry']['type']);
  }

  public function testUnrelatedPropertiesSurviveEditingAPoint(): void {
    $decoded = json_decode($this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->pointShape('5', '6', '', ['type' => 'Feature', 'properties' => ['foo' => 'bar']], 0)],
    ]), TRUE);

    $this->assertSame('bar', $decoded['features'][0]['properties']['foo']);
  }

  public function testLegacyWktValueIsPassedThroughUnchanged(): void {
    $this->assertSame('POINT(1 2)', $this->massage(['raw_passthrough' => 'POINT(1 2)']));
  }

  public function testEmptyEverythingProducesEmptyStringNotEmptyCollection(): void {
    $this->assertSame('', $this->massage(['preserved_features' => [], 'shapes' => []]));
  }

  public function testGeneratedValueIsLoadableByGeoPhp(): void {
    $json = $this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->pointShape('46.7298', '-117.1817')],
    ]);
    $geom = \Drupal::service('geofield.geophp')->load($json);
    $this->assertInstanceOf(\Geometry::class, $geom);

    $polygonJson = $this->massage([
      'preserved_features' => [],
      'shapes' => [0 => $this->lineOrPolygonShape('Polygon', [['0', '0'], ['0', '1'], ['1', '1']])],
    ]);
    $polygonGeom = \Drupal::service('geofield.geophp')->load($polygonJson);
    $this->assertInstanceOf(\Geometry::class, $polygonGeom);
  }

  public function testParseStoredValueSplitsSimpleShapesFromOther(): void {
    $value = json_encode([
      'type' => 'FeatureCollection',
      'features' => [
        // Polygon with a hole: not simple, preserved untouched.
        ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Polygon', 'coordinates' => [
          [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
          [[1, 1], [2, 1], [2, 2], [1, 2], [1, 1]],
        ]]],
        ['type' => 'Feature', 'properties' => ['location_description' => 'Home'], 'geometry' => ['type' => 'Point', 'coordinates' => [-117.18, 46.73]]],
        // MultiPoint: not simple, preserved untouched.
        ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'MultiPoint', 'coordinates' => [[0, 0], [1, 1]]]],
      ],
    ]);
    $parsed = $this->parseStored($value);
    $this->assertCount(1, $parsed['shapes']);
    $this->assertCount(2, $parsed['other']);
    $this->assertNull($parsed['passthrough']);
    $this->assertSame('Home', $parsed['shapes'][0]['description']);
  }

  public function testParseStoredValueDetectsPassthroughForWkt(): void {
    $parsed = $this->parseStored('POINT(1 2)');
    $this->assertSame('POINT(1 2)', $parsed['passthrough']);
    $this->assertSame([], $parsed['shapes']);
  }

  public function testParseStoredValueHandlesEmptyAndNull(): void {
    foreach ([NULL, ''] as $value) {
      $parsed = $this->parseStored($value);
      $this->assertSame([], $parsed['shapes']);
      $this->assertSame([], $parsed['other']);
      $this->assertNull($parsed['passthrough']);
    }
  }

}

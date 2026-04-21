<?php

namespace Drupal\Tests\geofield\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the geofield formatters functionality.
 *
 * @group geofield
 */
class GeofieldFormatterTest extends EntityKernelTestBase {

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['geofield'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'geofield',
      'entity_type' => $this->entityType,
      'type' => 'geofield',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
    ]
    )->save();

    FieldConfig::create([
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'field_name' => 'geofield',
      'label' => 'GeoField',
      'settings' => [
        'backend' => 'geofield_backend_default',
      ],
    ])->save();
  }

  /**
   * Tests geofield field default formatter.
   */
  public function testDefaultFormatter() {
    // Create the entity to be referenced.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $value = \Drupal::service('geofield.wkt_generator')->WktGenerateGeometry();
    $entity->geofield = [
      'value' => $value,
    ];
    $entity->save();

    // Verify the geofield field formatter's render array.
    $build = $entity->get('geofield')->view(['type' => 'geofield_default']);
    \Drupal::service('renderer')->renderRoot($build[0]);
    $this->assertEquals($value, $build[0]['#markup']);
  }

  /**
   * Tests geofield field LatLon formatter.
   *
   * @dataProvider latLonFormatterProvider
   */
  public function testLatLonFormatter($point, $format, $expected_value) {
    // Create the entity to be referenced.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);

    $entity->geofield = [
      'value' => $point,
    ];
    $entity->save();

    // Verify the geofield field formatter's render array.
    $build = $entity->get('geofield')->view([
      'type' => 'geofield_latlon',
      'settings' => ['output_format' => $format],
    ]);
    \Drupal::service('renderer')->renderRoot($build[0]);
    $this->assertEquals($expected_value, trim($build[0]['#markup']));
  }

  /**
   * Provides test data for testLatLonFormatter().
   */
  public static function latLonFormatterProvider(): array {
    return [
      'DMS Value' => [
        'POINT (40 -3)',
        'dms',
        "<span class=\"dms dms-lat\">
    3°
    0'
          0\"
        S
  </span>
      ,
    <span class=\"dms dms-lon\">
    40°
    0'
          0\"
        E
  </span>",
      ],
      'DM Value' => [
        'POINT (40 -3)',
        'dm',
        "<span class=\"dms dms-lat\">
    3°
    0.00000'
        S
  </span>
      ,
    <span class=\"dms dms-lon\">
    40°
    0.00000'
        E
  </span>",
      ],
      'LatLon Value' => [
        'POINT (40 -3)',
        'decimal',
        '<span class="latlon latlon-lat">-3</span>, <span class="latlon latlon-lon">40</span>',
      ],
      'DMS Value long' => [
        'POINT (85.24587 45.625358)',
        'dms',
        "<span class=\"dms dms-lat\">
    45°
    37'
          31\"
        N
  </span>
      ,
    <span class=\"dms dms-lon\">
    85°
    14'
          45\"
        E
  </span>",
      ],
      'DM Value long' => [
        'POINT (85.24587 45.625358)',
        'dm',
        "<span class=\"dms dms-lat\">
    45°
    37.51667'
        N
  </span>
      ,
    <span class=\"dms dms-lon\">
    85°
    14.75000'
        E
  </span>",
      ],
      'LatLon Value long' => [
        'POINT (85.24587 45.625358)',
        'decimal',
        '<span class="latlon latlon-lat">45.625358</span>, <span class="latlon latlon-lon">85.24587</span>',
      ],
      'DMS Arnedo' => [
        'POINT (-2.1021 42.2257)',
        'dms',
        "<span class=\"dms dms-lat\">
    42°
    13'
          33\"
        N
  </span>
      ,
    <span class=\"dms dms-lon\">
    2°
    6'
          8\"
        W
  </span>",
      ],
      'DM Arnedo' => [
        'POINT (-2.1021 42.2257)',
        'dm',
        "<span class=\"dms dms-lat\">
    42°
    13.55000'
        N
  </span>
      ,
    <span class=\"dms dms-lon\">
    2°
    6.13333'
        W
  </span>",
      ],
      'Decimal Arnedo' => [
        'POINT (-2.1021 42.2257)',
        'decimal',
        '<span class="latlon latlon-lat">42.2257</span>, <span class="latlon latlon-lon">-2.1021</span>',
      ],
      'Polygon' => [
        'POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))',
        'wkt',
        'POINT (25.454545454545 26.969696969697)',
      ],
    ];
  }

}

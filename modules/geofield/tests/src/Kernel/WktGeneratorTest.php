<?php

namespace Drupal\Tests\geofield\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests WktGenerator.
 *
 * @group geofield
 */
class WktGeneratorTest extends KernelTestBase {

  /**
   * WKT Generator service.
   *
   * @var \Drupal\geofield\WktGenerator
   */
  public $wktGenerator;

  /**
   * Generic WKT point regex.
   *
   * @var string
   */
  public $pointRegex = '/^POINT \([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)$/';

  /**
   * Generic WKT multipoint regex.
   *
   * @var string
   */
  public $multipointRegex = '/^MULTIPOINT \((\([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\), )*(\([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\))\)$/';

  /**
   * Generic WKT linestring regex.
   *
   * @var string
   */
  public $linestringRegex = '/^LINESTRING \(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)$/';

  /**
   * Generic WKT multilinestring regex.
   *
   * @var string
   */
  public $multilinestringRegex = '/^MULTILINESTRING \((\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\), )*\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)\)$/';

  /**
   * Generic WKT polygon regex.
   *
   * @var string
   */
  public $polygonRegex = '/^POLYGON \(\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)\)$/';

  /**
   * Generic WKT multipolygon regex.
   *
   * @var string
   */
  public $multipolygonRegex = '/^MULTIPOLYGON \((\(\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)\), )*\(\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)\)\)$/';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'geofield',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->wktGenerator = \Drupal::service('geofield.wkt_generator');
  }

  /**
   * Tests the generation of WKT points and multipoints.
   */
  public function testPoint() {
    $point = $this->wktGenerator->wktGeneratePoint(['3', '4']);
    $this->assertEquals('POINT (3 4)', $point, 'Point generated properly');

    $point = $this->wktGenerator->wktGeneratePoint();
    $match = preg_match($this->pointRegex, $point);
    $this->assertNotEmpty($match, 'Point generated properly');

    $multipoint = $this->wktGenerator->wktGenerateMultipoint();
    $match = preg_match($this->multipointRegex, $multipoint);
    $this->assertNotEmpty($match, 'Multipoint generated properly');
  }

  /**
   * Tests the generation of WKT linestrings and multilinestrings.
   */
  public function testLinestring() {
    $linestring = $this->wktGenerator->wktGenerateLinestring();
    $match = preg_match($this->linestringRegex, $linestring);
    $this->assertNotEmpty($match, 'Linestring generated properly');

    $linestring = $this->wktGenerator->wktGenerateLinestring([7.34, -45.66]);
    $match = preg_match('/^LINESTRING \(7.34 -45.66, ([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)$/', $linestring);
    $this->assertNotEmpty($match, 'Linestring generated properly');

    $linestring = $this->wktGenerator->wktGenerateLinestring(NULL, 9);
    $match = preg_match('/^LINESTRING \(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, ){8}[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)$/', $linestring);
    $this->assertNotEmpty($match, 'Linestring generated properly');

    $linestring = $this->wktGenerator->wktGenerateLinestring([7, 45], 6);
    $match = preg_match('/^LINESTRING \(7 45, ([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, ){4}[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)$/', $linestring);
    $this->assertNotEmpty($match, 'Linestring generated properly');

    $multilinestring = $this->wktGenerator->wktGenerateMultilinestring();
    $match = preg_match($this->multilinestringRegex, $multilinestring);
    $this->assertNotEmpty($match, 'Multilinestring generated properly');
  }

  /**
   * Tests the generation of WKT polygons and multipolygons.
   */
  public function testPolygon() {
    $polygon = $this->wktGenerator->wktGeneratePolygon();
    $match = preg_match($this->polygonRegex, $polygon);
    $this->assertNotEmpty($match, 'Polygon generated properly');

    $polygon = $this->wktGenerator->wktGeneratePolygon([7.34, -45.66]);
    $match = preg_match('/^POLYGON \(\(7.34 -45.66, ([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, )*7.34 -45.66\)\)$/', $polygon);
    $this->assertNotEmpty($match, 'Polygon generated properly');

    $polygon = $this->wktGenerator->wktGeneratePolygon(NULL, 9);
    $match = preg_match('/^POLYGON \(\(([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, ){9}[-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\)\)$/', $polygon);
    $this->assertNotEmpty($match, 'Polygon generated properly');

    $polygon = $this->wktGenerator->wktGeneratePolygon([7, 45], 6);
    $match = preg_match('/^POLYGON \(\(7 45, ([-]?[0-9]*\.?[0-9]+ [-]?[0-9]*\.?[0-9]+\, ){5}7 45\)\)$/', $polygon);
    $this->assertNotEmpty($match, 'Polygon generated properly');

    $multipolygon = $this->wktGenerator->wktGenerateMultipolygon();
    $match = preg_match($this->multipolygonRegex, $multipolygon);
    $this->assertNotEmpty($match, 'Multipolygon generated properly');
  }

  /**
   * Tests the generation of random WKT geometries.
   */
  public function testRandomGeometry() {
    $find = FALSE;
    $geometry = $this->wktGenerator->wktGenerateGeometry();
    $patterns = [
      $this->pointRegex,
      $this->multipointRegex,
      $this->linestringRegex,
      $this->multilinestringRegex,
      $this->polygonRegex,
      $this->multipolygonRegex,
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $geometry)) {
        $find = TRUE;
        break;
      }
    }

    $this->assertTrue($find, 'Random geometry generated properly');
  }

}

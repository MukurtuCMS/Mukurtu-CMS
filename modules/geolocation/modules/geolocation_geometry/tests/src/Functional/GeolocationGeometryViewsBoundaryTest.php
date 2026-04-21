<?php

namespace Drupal\Tests\geolocation_geometry\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the grid style plugin.
 *
 * @group geolocation
 */
class GeolocationGeometryViewsBoundaryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'views',
    'geolocation',
    'geolocation_geometry',
    'geolocation_geometry_demo',
    'geolocation_geometry_test_views',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['geolocation_geometry_test_boundary'];

  /**
   * ID of the geolocation field in this test.
   *
   * @var string
   */
  protected $viewsPath = 'geolocation-geometry-test-boundary';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(get_class($this), ['geolocation_geometry_test_views']);
  }

  /**
   * Tests the boundary filter.
   */
  public function testBoundaryNoLocations() {
    $this->drupalGet($this->viewsPath);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the boundary filter.
   *
   * It's currently locked to filter boundary of NE80,80 to SW20,20.
   */
  public function testBoundaryLocations() {
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('node');

    $entity_test_storage->create([
      'id' => 1,
      'title' => 'Containing Polygon',
      'body' => 'test test',
      'type' => 'geolocation_geometry_demo',
      'field_geolocation_geometry_polyg' => [
        'wkt' => 'POLYGON((170 -44, 169 -44, 169 -45, 170 -45, 170 -44))',
      ],
    ])->save();
    $entity_test_storage->create([
      'id' => 2,
      'title' => 'Intersecting Polygon',
      'body' => 'test foobar',
      'type' => 'geolocation_geometry_demo',
      'field_geolocation_geometry_polyg' => [
        'wkt' => 'POLYGON((171.1217044 -43.6891741, 168.1217044 -43.6891741, 168.1217044 -45.6891741, 171.1217044 -45.6891741, 171.1217044 -43.6891741))',
      ],
    ])->save();
    $entity_test_storage->create([
      'id' => 3,
      'title' => 'Outside Polygon',
      'body' => 'test foobar',
      'type' => 'geolocation_geometry_demo',
      'field_geolocation_geometry_polyg' => [
        'wkt' => 'POLYGON((10 20, 8 20, 8 22, 10 22, 10 20))',
      ],
    ])->save();

    $this->drupalGet($this->viewsPath);
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('Wanaka');

    $this->assertSession()->responseContains('Containing Polygon');
    $this->assertSession()->responseNotContains('Intersecting Polygon');
    $this->assertSession()->responseNotContains('Outside Polygon');
  }

}

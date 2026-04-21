<?php

namespace Drupal\Tests\geolocation\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the grid style plugin.
 *
 * @group geolocation
 */
class GeolocationViewsCommonMapTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'views',
    'taxonomy',
    'geolocation',
    'geolocation_demo',
    'geolocation_google_maps',
    'geolocation_google_maps_demo',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the boundary filter.
   */
  public function testStaticCommonMap() {
    $this->drupalGet('geolocation-demo/common-map');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the boundary filter.
   */
  public function testAjaxCommonMap() {
    $this->drupalGet('geolocation-demo/common-map-ajax');
    $this->assertSession()->statusCodeEquals(200);
  }

}

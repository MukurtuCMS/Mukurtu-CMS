<?php

namespace Drupal\Tests\geolocation_leaflet\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the grid style plugin.
 *
 * @group geolocation
 */
class LeafletCommonMapTest extends BrowserTestBase {

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
    'geolocation_leaflet',
    'geolocation_leaflet_demo',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the boundary filter.
   */
  public function testCommonMap() {
    $this->drupalGet('geolocation-demo/leaflet-commonmap');
    $this->assertSession()->statusCodeEquals(200);
  }

}

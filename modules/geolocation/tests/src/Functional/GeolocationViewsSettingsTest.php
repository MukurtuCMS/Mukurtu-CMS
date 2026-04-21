<?php

namespace Drupal\Tests\geolocation\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Views integration.
 *
 * @group geolocation
 */
class GeolocationViewsSettingsTest extends BrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
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
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the Views admin UI and field handlers.
   */
  public function testViewsMapSettings() {
    $permissions = [
      'access administration pages',
      'administer views',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/views/view/geolocation_demo_common_map');

    // Add click sorting for all fields where this is possible.
    $this->clickLink('Settings');
    $edit = [
      'style_options[centre][fit_bounds][enable]' => 1,
    ];
    $this->submitForm($edit, 'Apply');
  }

  /**
   * Tests the Views admin UI and field handlers.
   */
  public function testViewsProximitySettings() {
    $permissions = [
      'access administration pages',
      'administer views',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/views/view/geolocation_demo_proximity_filter_sort');

    // Add click sorting for all fields where this is possible.
    $this->clickLink('Content: Geolocation Demo Single - Proximity Form');
    $edit = [
      'options[center][coordinates][enable]' => 1,
    ];
    $this->submitForm($edit, 'Apply');
  }

}

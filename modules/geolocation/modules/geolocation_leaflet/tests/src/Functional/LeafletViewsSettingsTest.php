<?php

namespace Drupal\Tests\geolocation_leaflet\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Views integration.
 *
 * @group geolocation
 */
class LeafletViewsSettingsTest extends BrowserTestBase {

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
    'geolocation_leaflet',
    'geolocation_leaflet_demo',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the Views admin UI and field handlers.
   */
  public function testViewsAdmin() {
    $permissions = [
      'access administration pages',
      'administer views',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/views/view/geolocation_demo_leaflet_common_map');

    // Add click sorting for all fields where this is possible.
    $this->clickLink('Settings');
    $edit = [
      'style_options[centre][fit_bounds][enable]' => 1,
    ];
    $this->submitForm($edit, 'Apply');
  }

}

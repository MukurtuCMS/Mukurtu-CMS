<?php

namespace Drupal\Tests\geolocation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the grid style plugin.
 *
 * @group geolocation
 */
class GeolocationSchemaCoverageTest extends BrowserTestBase {

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
    'geolocation_google_static_maps',
    'geolocation_google_maps_demo',
    'geolocation_leaflet',
    'geolocation_yandex',
    'geolocation_here',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test MapProviders.
   */
  public function testMapProvidersDefaults() {
    /** @var \Drupal\geolocation\MapProviderManager $mapProviderManager */
    $mapProviderManager = \Drupal::service('plugin.manager.geolocation.mapprovider');
    $mapProviderIds = $mapProviderManager->getDefinitions();

    $view = View::load('geolocation_demo_common_map');
    foreach ($mapProviderIds as $mapProviderId => $definition) {
      $mapProvider = $mapProviderManager->getMapProvider($mapProviderId);

      $display = &$view->getDisplay('default');
      $display['display_options']['style']['options']['map_provider_id'] = $mapProviderId;
      $display['display_options']['style']['options']['map_provider_settings'] = $mapProvider->getSettings([]);
      $view->save();

      $this->drupalGet('geolocation-demo/common-map');
      $this->assertSession()->statusCodeEquals(200);
    }

  }

  /**
   * Test MapFeatures with providers.
   */
  public function testMapProvidersWithMapFeatures() {
    /** @var \Drupal\geolocation\MapFeatureManager $mapFeatureManager */
    $mapFeatureManager = \Drupal::service('plugin.manager.geolocation.mapfeature');

    /** @var \Drupal\geolocation\MapProviderManager $mapProviderManager */
    $mapProviderManager = \Drupal::service('plugin.manager.geolocation.mapprovider');

    $view = View::load('geolocation_demo_common_map');
    foreach ($mapProviderManager->getDefinitions() as $mapProviderId => $definition) {
      $mapProvider = $mapProviderManager->getMapProvider($mapProviderId);

      $display = &$view->getDisplay('default');
      $display['display_options']['style']['options']['map_provider_id'] = $mapProviderId;
      $display['display_options']['style']['options']['map_provider_settings'] = $mapProvider->getSettings([]);
      $display['display_options']['style']['options']['map_provider_settings']['map_features'] = [];
      foreach ($mapFeatureManager->getMapFeaturesByMapType($mapProviderId) as $mapFeatureId => $mapFeatureDefinition) {
        $mapFeature = $mapFeatureManager->getMapFeature($mapFeatureId);
        $display['display_options']['style']['options']['map_provider_settings']['map_features'][$mapFeatureId] = [
          'enabled' => TRUE,
          'settings' => $mapFeature->getSettings([]),
        ];
      }
      $view->save();

      $this->drupalGet('geolocation-demo/common-map');
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}

<?php

namespace Drupal\Tests\geolocation_google_maps\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the creation of geolocation fields.
 *
 * @group geolocation
 */
class GeolocationGoogleMapsGeocodingTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'geolocation',
    'geolocation_google_maps',
    'geolocation_google_maps_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test geocoding.
   */
  public function testGeocoder() {
    /** @var \Drupal\geolocation\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('plugin.manager.geolocation.geocoder')->getGeocoder('google_geocoding_api');
    $location = $geocoder->geocode('H.Congreso de la Unión 66, El Parque, Venustiano Carranza, 15960 Ciudad de México, CDMX, Mexiko');
    $this->assertArrayHasKey('location', $location);
  }

  /**
   * Test reverse geocoding.
   */
  public function testReverseGeocoder() {
    /** @var \Drupal\geolocation\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('plugin.manager.geolocation.geocoder')->getGeocoder('google_geocoding_api');
    $latitude = 38.56;
    $longitude = 68.773889;
    $address = $geocoder->reverseGeocode($latitude, $longitude);
    $this->assertArrayHasKey('atomics', $address);
  }

}

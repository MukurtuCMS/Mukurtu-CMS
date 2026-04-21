<?php

namespace Drupal\Tests\geocoder\Kernel;

use Drupal\geocoder\Entity\GeocoderProvider;
use Drupal\KernelTests\KernelTestBase;
use Geocoder\Query\GeocodeQuery;

/**
 * Tests basic Geocoder functionality.
 *
 * @group geocoder
 */
class GeocoderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['geocoder', 'geocoder_test_provider'];

  /**
   * Our test provider.
   *
   * @var \Drupal\geocoder\GeocoderProviderInterface
   */
  protected $provider;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->provider = GeocoderProvider::create([
      'id' => 'random',
      'plugin' => 'random',
    ]);
    $this->provider->save();
  }

  /**
   * Tests the random provider geocoding.
   */
  public function testRandomGeocode() {
    /** @var \Drupal\geocoder\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('geocoder');
    $this->assertNotEmpty($geocoder->geocode('123 Foo Street', [
      $this->provider,
    ]));
  }

  /**
   * Tests the random provider geocoding with passing the provider as a string.
   */
  public function testRandomGeocodeWithString() {
    /** @var \Drupal\geocoder\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('geocoder');
    $this->assertNotEmpty($geocoder->geocode('123 Foo Street', [
      'random',
    ]));
  }

  /**
   * Tests geocoding a GeocodeQuery.
   *
   * Tries the "random" Geocoder which cannot geocode a GeocodeQuery.
   */
  public function testRandomGeocodeWithGeocodeQuery() {
    /** @var \Drupal\geocoder\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('geocoder');
    $this->assertEmpty($geocoder->geocode(GeocodeQuery::create('123 Foo Street'), [
      'random',
    ]));
  }

  /**
   * Tests geocoding a GeocodeQuery.
   *
   * Tries the "geocoder_test_provider" Geocoder which can geocode a
   * GeocodeQuery.
   */
  public function testGeocodeWithGeocodeQuery() {
    GeocoderProvider::create([
      'id'     => 'geocoder_test_provider',
      'plugin' => 'geocoder_test_provider',
    ])->save();

    /** @var \Drupal\geocoder\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('geocoder');
    $this->assertNotEmpty($geocoder->geocode(GeocodeQuery::create('123 Foo Street'), [
      'geocoder_test_provider',
    ]));
  }

}

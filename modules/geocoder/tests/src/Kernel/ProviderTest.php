<?php

namespace Drupal\Tests\geocoder\Kernel;

use Drupal\geocoder\Entity\GeocoderProvider;
use Drupal\geocoder\ProviderGeocoderPhpInterface;
use Drupal\KernelTests\KernelTestBase;
use Geocoder\Collection;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Tests integration with PHP Geocoder provider.
 *
 * @group Geocoder
 */
class ProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'geocoder',
    'geocoder_test_provider',
  ];

  /**
   * Our test provider.
   *
   * @var \Drupal\geocoder\GeocoderProviderInterface
   */
  protected $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->provider = GeocoderProvider::create([
      'id' => 'geocoder_test_provider',
      'plugin' => 'geocoder_test_provider',
    ]);
    $this->provider->save();
  }

  /**
   * Test geocode string via geocoder service.
   */
  public function testGeocodeService(): void {
    $geocoder = \Drupal::service('geocoder');
    $result = $geocoder->geocode('Test string', [
      $this->provider,
    ]);
    assert($result instanceof Collection);
    $this->assertEquals(75020, $result->first()->getPostalCode());
  }

  /**
   * Test reverse geocode string via geocoder service.
   */
  public function testReverseService(): void {
    $geocoder = \Drupal::service('geocoder');
    $result = $geocoder->reverse('1', '1', [
      $this->provider,
    ]);
    assert($result instanceof Collection);
    $this->assertEquals(75020, $result->first()->getPostalCode());
  }

  /**
   * Test directly geocoding query via provider.
   */
  public function testGeocodeQuery(): void {
    $query = GeocodeQuery::create('Test string');
    $plugin = $this->provider->getPlugin();
    assert($plugin instanceof ProviderGeocoderPhpInterface);
    $result = $plugin->geocodeQuery($query);
    assert($result instanceof Collection);
    $this->assertEquals(75020, $result->first()->getPostalCode());
  }

  /**
   * Test directly reverse query via provider.
   */
  public function testReverseQuery(): void {
    $query = ReverseQuery::create(new Coordinates(1, 1));
    /** @var \Drupal\geocoder\ProviderGeocoderPhpInterface $plugin */
    $plugin = $this->provider->getPlugin();
    $result = $plugin->reverseQuery($query);
    assert($result instanceof Collection);
    $this->assertEquals(75020, $result->first()->getPostalCode());
  }

}

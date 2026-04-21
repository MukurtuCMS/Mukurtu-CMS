<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\Core\Locale\CountryManager;
use Drupal\geocoder\ProviderBase;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\AdminLevelCollection;

/**
 * A geocoder provider that resolves random addresses.
 *
 * @GeocoderProvider(
 *  id = "random",
 *  name = "Random"
 * )
 */
class Random extends ProviderBase {

  /**
   * The address factory.
   *
   * @var \Geocoder\Model\Address
   */
  protected $addressFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function doGeocode($source) {
    return new AddressCollection(
      [
        $this->getAddressFactory()->createFromArray($this->getRandomResult()),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function doReverse($latitude, $longitude) {
    $result = $this->getRandomResult();
    $result['latitude'] = $latitude;
    $result['longitude'] = $longitude;

    return new AddressCollection(
      [
        $this->getAddressFactory()->createFromArray($result),
      ]
    );
  }

  /**
   * Get Random Country info.
   *
   * @param string|null $type
   *   The type string, if set.
   * */
  private function getRandomCountryInfo(?string $type = NULL) {
    $manager = new CountryManager($this->getModuleHandler());
    $countries = $manager->getList();
    uksort($countries, function () {
      return rand() <=> rand();
    });
    $country = array_slice($countries, 0, 1);

    $value = [
      'code' => key($country),
      'name' => reset($country),
    ];

    if (is_null($type)) {
      return $value;
    }

    return $value[$type] ?? $value;
  }

  /**
   * Generate a fake random address array.
   *
   * @todo [cc]: Tidi-up, document, etc.
   *
   * @return array
   *   Return array of dta such as latitude, longitude, etc.
   */
  protected function getRandomResult() {
    $country = $this->getRandomCountryInfo();
    $streetTypes = [
      'street',
      'avenue',
      'square',
      'road',
      'way',
      'drive',
      'lane',
      'place',
      'hill',
      'gardens',
      'park',
    ];

    return [
      'latitude' => mt_rand(0, 90) + mt_rand() / mt_getrandmax(),
      'longitude' => mt_rand(-180, 180) + mt_rand() / mt_getrandmax(),
      'streetName' => $this->getRandomCountryInfo('name') . ' ' . $streetTypes[mt_rand(0, count($streetTypes) - 1)],
      'streetNumber' => (string) mt_rand(1, 1000),
      'postalCode' => (string) mt_rand(1, 1000),
      'locality' => sha1(mt_rand() / mt_getrandmax()),
      'country' => (string) $country['name'],
      'countryCode' => $country['code'],
    ];
  }

  /**
   * Returns the address factory.
   *
   * @return \Geocoder\Model\Address
   *   Return the address Factory.
   */
  protected function getAddressFactory() {
    if (!isset($this->addressFactory)) {
      $this->addressFactory = new Address('', new AdminLevelCollection());
    }

    return $this->addressFactory;
  }

  /**
   * Returns the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   Return the module Handler.
   */
  protected function getModuleHandler() {
    if (!isset($this->moduleHandler)) {
      $this->moduleHandler = \Drupal::moduleHandler();
    }

    return $this->moduleHandler;
  }

}

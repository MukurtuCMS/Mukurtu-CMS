<?php

namespace Drupal\geocoder_test_provider\Geocoder\Provider;

use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Provides a Mock geocoder handler for use with the MokProvider plugin.
 *
 * @see \Drupal\geocoder_test_provider\Plugin\Geocoder\Provider\MockProvider.
 */
class MockProvider implements Provider {

  /**
   * Name of geocoder handler.
   *
   * @var string
   */
  protected $name;

  /**
   * Result to return.
   *
   * @var \Geocoder\Location
   */
  public $result = [];

  /**
   * Construct defaults for MockProvider class.
   */
  public function __construct() {
    $this->name = 'test_name';
    $this->result = [
      Address::createFromArray([
        'providedBy' => 'n/a',
        'latitude' => 48.8631507,
        'longitude' => 2.3889114,
        'bounds' => [
          'south' => 48.8631507,
          'west' => 2.3889114,
          'north' => 48.8631507,
          'east' => 2.388911,
        ],
        'streetNumber' => '10',
        'streetName' => 'Avenue Gambetta',
        'postalCode' => '75020',
        'locality' => 'Paris',
        'subLocality' => '20e Arrondissement',
        'adminLevels' => [
          1 => [
            'name' => 'Ile-de-France',
            'code' => 'Ile-de-France',
            'level' => 1,
          ],
        ],
        'country' => 'France',
        'countryCode' => 'FR',
        'timezone' => NULL,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function geocodeQuery(GeocodeQuery $query): Collection {
    return $this->returnResult();
  }

  /**
   * {@inheritdoc}
   */
  public function reverseQuery(ReverseQuery $query): Collection {
    return $this->returnResult();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Wrap result location into a Collection to be returned.
   *
   * @return \Geocoder\Collection
   *   The returned Collection.
   */
  private function returnResult(): Collection {
    return new AddressCollection($this->result);
  }

}

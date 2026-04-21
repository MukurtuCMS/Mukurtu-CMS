<?php

namespace Drupal\geocoder_address\Plugin\Geocoder\Preprocessor;

use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\geocoder_address\AddressService;
use Drupal\geocoder_field\PreprocessorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a geocoder preprocessor plugin for address fields.
 *
 * @GeocoderPreprocessor(
 *   id = "address",
 *   name = "Address",
 *   field_types = {
 *     "address"
 *   }
 * )
 */
class Address extends PreprocessorBase {

  /**
   * The address service.
   *
   * @var \Drupal\geocoder_address\AddressService
   */
  protected $addressService;

  /**
   * PreprocessorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The Country Manager service.
   * @param \Drupal\geocoder_address\AddressService $address_service
   *   The Geocoder Address service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CountryManagerInterface $country_manager, AddressService $address_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $country_manager);
    $this->addressService = $address_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('country_manager'),
      $container->get('geocoder_address.address')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess() {
    parent::preprocess();

    foreach ($this->field->getValue() as $delta => $value) {
      $address = '';
      // Use the Address API to format the array of values into a string
      // that can be sent to the geocoder service.
      if (is_array($value)) {
        $address = $this->addressService->addressArrayToGeoString($value);
      }
      $value['value'] = $address;
      $this->field->set($delta, $value);
    }

    return $this;
  }

}

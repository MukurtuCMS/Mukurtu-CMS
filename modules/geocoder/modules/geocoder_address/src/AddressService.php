<?php

namespace Drupal\geocoder_address;

use CommerceGuys\Addressing\Address as AddressingAddress;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use Drupal\address\Element\Address as ElementAddress;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Generate an AddressService.
 *
 * @package Drupal\geocoder_address
 */
class AddressService extends ServiceProviderBase {

  /**
   * The address format repository.
   *
   * @var \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface
   */
  protected AddressFormatRepositoryInterface $addressFormatRepository;

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected CountryRepositoryInterface $countryRepository;

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface
   */
  protected SubdivisionRepositoryInterface $subdivisionRepository;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * AddressService constructor.
   *
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface $address_format_repository
   *   The address format repository.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The subdivision repository.
   * @param \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface $subdivision_repository
   *   The postal label formatter.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(AddressFormatRepositoryInterface $address_format_repository, CountryRepositoryInterface $country_repository, SubdivisionRepositoryInterface $subdivision_repository, ModuleHandlerInterface $module_handler) {
    $this->addressFormatRepository = $address_format_repository;
    $this->countryRepository = $country_repository;
    $this->subdivisionRepository = $subdivision_repository;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Set formatter.
   *
   * The postal formatter seems to return the kind of value the geocoders
   * expect to receive. Other situations might use the DefaultFormatter()
   * instead.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $countrycode
   *   The country code.
   * @param string $formatter
   *   The type of formatter to return, 'postal' or 'default'.
   *
   * @return mixed
   *   - 'postal': \CommerceGuys\Addressing\Formatter\PostalLabelFormatter
   *     A formatter that will convert the input values into a postal label.
   *   - 'default': \CommerceGuys\Addressing\Formatter\DefaultFormatter
   *     The default formatter.
   */
  public function getFormatter($langcode, $countrycode = 'US', $formatter = 'default') {
    $default_options = [
      'locale' => $langcode,
      'origin_country' => $countrycode,
    ];
    switch ($formatter) {
      case 'postal':
        return new PostalLabelFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository, $default_options);

      default:
        return new DefaultFormatter($this->addressFormatRepository, $this->countryRepository, $this->subdivisionRepository, $default_options);

    }
  }

  /**
   * Convert an address array into a string address suitable for geocoding.
   *
   * Expects an array structured like the Address module as the input values.
   *
   * @param array $values
   *   An array keyed by any combination of the following:
   *   - given_name
   *   - additional_name
   *   - family_name
   *   - organization
   *   - address_line1
   *   - address_line2
   *   - postal_code
   *   - sorting_code
   *   - dependent_locality
   *   - locality
   *   - administrative_area
   *   - country_code
   *   - langcode.
   *
   * @return string
   *   The string representation of the address suitable for submission to a
   *   geocoder service.
   */
  public function addressArrayToGeoString(array $values) {

    // Make sure the address_array has all values populated.
    $values = ElementAddress::applyDefaults($values);

    $this->moduleHandler->alter('geocoder_address_values', $values);

    // Without a country code this won't work.
    if (empty($values['country_code'])) {
      return '';
    }

    // Use the Address formatter to create a string ordered appropriately
    // for the country in the address.
    $address = new AddressingAddress();
    $address = $values['country_code'] ? $address->withCountryCode($values['country_code']) : $address;
    $address = $values['postal_code'] ? $address->withPostalCode($values['postal_code']) : $address;
    $address = $values['administrative_area'] ? $address->withAdministrativeArea($values['administrative_area']) : $address;
    $address = $values['dependent_locality'] ? $address->withDependentLocality($values['dependent_locality']) : $address;
    $address = $values['locality'] ? $address->withLocality($values['locality']) : $address;
    $address = $values['address_line1'] ? $address->withAddressLine1($values['address_line1']) : $address;
    $address = $values['address_line2'] ? $address->withAddressLine2($values['address_line2']) : $address;

    $countrycode = $values['country_code'] ?? NULL;
    $langcode = !empty($values['langcode']) ? $values['langcode'] : 'en';

    // Get the formatted address.
    /** @var \CommerceGuys\Addressing\Formatter\PostalLabelFormatter $formatter */
    $formatter = $this->getFormatter($langcode, $countrycode, 'postal');
    $address_string = $formatter->format($address);

    // Clean up the returned multiline address to turn it into a single line of
    // text.
    $address_string = str_replace("\n", ', ', $address_string);
    $address_string = str_replace("<br>", ', ', $address_string);
    $address_string = strip_tags($address_string);

    // In case of a country-only address, use the full country name.
    if (strlen($address_string) === 0 && strlen($countrycode) !== 0) {
      $address_string = $this->countryRepository->get($countrycode)->getName();
    }
    elseif (strlen($countrycode) !== 0) {
      // Otherwise add Country code suffix, if defined.
      $address_string .= ' ' . $countrycode;
    }

    return $address_string;
  }

}

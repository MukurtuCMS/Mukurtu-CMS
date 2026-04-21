<?php

/**
 * @file
 * API documentation for Geocoder module.
 */

/**
 * Alter the Address String to Geocode.
 *
 * Allow other modules to adjust the address string.
 *
 * @param string $address_string
 *   The address string to geocode.
 */
function hook_geocode_address_string_alter(string &$address_string) {
  // Make custom alterations to adjust the address string.
}

/**
 * Alter the address GeocodeQuery.
 *
 * Allow other modules to adjust the address GeocodeQuery.
 *
 * @param Geocoder\Query\GeocodeQuery $address
 *   The address query to geocode.
 */
function hook_geocode_address_geocode_query(Geocoder\Query\GeocodeQuery $address) {
}

/**
 * Alter the Coordinates to Reverse Geocode.
 *
 * Allow others modules to adjust the Coordinates to Reverse Geocode.
 *
 * @param string $latitude
 *   The latitude.
 * @param string $longitude
 *   The longitude.
 */
function hook_reverse_geocode_coordinates_alter(string &$latitude, string &$longitude) {
  // Make custom alterations to the Coordinates to Reverse Geocode.
}

/**
 * Alter the Country Code in setting the Address field from Geojson.
 *
 * @param string $country_code
 *   The country code.
 * @param array $geojson_array
 *   The geojson array.
 *
 * @see DumperPluginManager->setCountryFromGeojson
 */
function hook_geocode_country_code_alter(string &$country_code, array $geojson_array) {
  // Make custom alterations to the Country Code.
}

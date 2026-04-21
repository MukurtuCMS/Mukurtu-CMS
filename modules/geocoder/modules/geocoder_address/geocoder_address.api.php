<?php

/**
 * @file
 * API documentation for Geocoder module.
 */

/**
 * Alter the Address Values to Geocode.
 *
 * Allow other modules to adjust the address values.
 *
 * @param array $values
 *   The array of address values to geocode.
 */
function hook_geocoder_address_values_alter(array &$values) {
  // Make custom alterations to adjust the address values array.
}

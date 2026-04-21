<?php

/**
 * @file
 * Hooks provided by the Geocoder Field module.
 */

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Alter the List of Field types objects of Geocoding operations.
 *
 * Modules may implement this hook to alter the list of possible Geocoding
 * Field Types.
 *
 * @param array $source_fields_types
 *   The list of possible Geocoding Field Types.
 *
 * @see \Drupal\search_api\Backend\BackendPluginBase
 */
function hook_geocode_source_fields_alter(array &$source_fields_types) {
  array_push($source_fields_types,
    "my_new_field_1",
    "my_new_field_2"
  );
}

/**
 * Alter the List of Field types objects of Reverse Geocoding operations.
 *
 * Modules may implement this hook to alter the list of Reverse Geocoding
 * Field Types.
 *
 * @param array $source_fields_types
 *   The list of possible Reverse Geocoding Field Types.
 *
 * @see \Drupal\search_api\Backend\BackendPluginBase
 */
function hook_reverse_geocode_source_fields_alter(array &$source_fields_types) {
  array_push($source_fields_types,
    "my_new_field_1",
    "my_new_field_2"
  );
}

/**
 * Alter the Entity Field Address String to Geocode on Presave.
 *
 * @param string $address_string
 *   The address string to geocode.
 * @param \Drupal\Core\Field\FieldItemListInterface $field
 *   The field object of geocode op.
 */
function hook_geocode_entity_field_address_string_alter(string &$address_string, FieldItemListInterface $field) {
  // Make custom alterations to adjust the address string.
}

/**
 * Alter the Entity Field Coordinates to Reverse Geocode.
 *
 * @param string $latitude
 *   The latitude.
 * @param string $longitude
 *   The longitude.
 * @param \Drupal\Core\Field\FieldItemListInterface $field
 *   The field object of geocode op.
 */
function hook_reverse_geocode_entity_field_coordinates_alter(string &$latitude, string &$longitude, FieldItemListInterface $field) {
  // Make custom alterations to the Coordinates to Reverse Geocode.
}

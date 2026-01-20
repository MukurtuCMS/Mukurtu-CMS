<?php

/**
 * @file
 * Hooks provided by the Mukurtu Taxonomy module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of taxonomy fields that should be indexed for search.
 *
 * This hook allows modules to modify the list of taxonomy term reference
 * fields that will have their UUIDs indexed in the search index. This is used
 * to enable browsing/filtering of content by taxonomy terms on canonical
 * taxonomy term pages.
 *
 * By default, only 'field_category' and 'field_keywords' are indexed.
 *
 * @param array &$allowed_fields
 *   An array of field names (machine names) that should be indexed. Modules
 *   can add or remove field names from this array.
 *
 * @see \Drupal\mukurtu_taxonomy\EventSubscriber\TaxonomyFieldSearchIndexSubscriber::indexTaxonomyField()
 *
 * Example usage:
 * @code
 * /**
 *  * Implements hook_mukurtu_taxonomy_indexed_fields_alter().
 *  *\/
 * function mymodule_mukurtu_taxonomy_indexed_fields_alter(array &$allowed_fields) {
 *   // Add a custom taxonomy field to be indexed.
 *   $allowed_fields[] = 'field_custom_taxonomy';
 *
 *   // Remove field_category from being indexed.
 *   $key = array_search('field_category', $allowed_fields);
 *   if ($key !== FALSE) {
 *     unset($allowed_fields[$key]);
 *   }
 * }
 * @endcode
 */
function hook_mukurtu_taxonomy_indexed_fields_alter(array &$allowed_fields) {
  // Add your custom taxonomy fields here.
  $allowed_fields[] = 'field_my_custom_taxonomy';
}

/**
 * @} End of "addtogroup hooks".
 */

<?php

/**
 * @file
 * Views integration for the Mukurtu Dictionary module.
 */

/**
 * Implements hook_views_data_alter().
 */
function mukurtu_dictionary_views_data_alter(array &$data) {
  // Add our custom sort plugin to the field_glossary_entry field in the
  // Mukurtu Dictionary Index.
  $index_table = 'search_api_index_mukurtu_dictionary_index';
  if (isset($data[$index_table]['field_glossary_entry']['sort'])) {
    // Add our custom glossary order sort plugin as an additional option.
    $data[$index_table]['field_glossary_entry']['sort']['id'] = 'glossary_custom_order';
  }
}

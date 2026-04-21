<?php

/**
 * @file
 * Provide Views integration for Search API Location.
 */

use Drupal\search_api\Entity\Index;

/**
 * Implements hook_views_data_alter().
 */
function geolocation_search_api_views_data_alter(&$data) {
  /** @var \Drupal\search_api\IndexInterface $index */
  foreach (Index::loadMultiple() as $index) {
    $table = &$data['search_api_index_' . $index->id()];

    /** @var \Drupal\search_api\Item\FieldInterface $field */
    foreach ($index->getFields(TRUE) as $field_id => $field) {
      if ($field->getType() == 'location') {
        $field_alias = _search_api_location_views_get_field_alias($field_id, $table);
        $field_alias_boundary = $field_alias . '_boundary';

        // Add boundary handlers.
        $table[$field_alias_boundary] = [
          'group' => $field->getLabel(),
          'title' => t('Boundary (@field_name)', ['@field_name' => $field_alias]),
          'help' => $field->getDescription(),
          'filter' => [
            'id' => 'geolocation_search_api_filter_boundary',
            'allow empty' => TRUE,
            'real field' => $field_alias,
          ],
        ];
      }
    }
  }
}

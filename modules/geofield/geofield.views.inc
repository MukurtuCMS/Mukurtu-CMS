<?php

/**
 * @file
 * Views hook implementations.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function geofield_field_views_data(FieldStorageConfigInterface $field_storage) {

  // @todo handle base table fields, as this code only covers configurable fields.
  // @see https://www.drupal.org/node/2489476.
  $data = views_field_default_views_data($field_storage);

  $field_name = $field_storage->getName();
  $entity_type_id = $field_storage->getTargetEntityTypeId();
  $field_label = NULL;

  foreach ($data as $table_data) {
    if (isset($table_data[$field_name])) {
      $field_label = $table_data[$field_name]['title'];
      break;
    }
  }
  foreach ($data as $table_name => $table_data) {
    $data[$table_name][$field_name . '_proximity'] = [
      'group' => $table_data[$field_name . '_value']['group'],
      'title' => t('@label Proximity (@field_name)', [
        '@label' => $field_label,
        '@field_name' => $field_name,
      ]),
      'help' => $table_data[$field_name . '_value']['help'],
      'field' => [
        'id' => 'geofield_proximity_field',
        'real field' => $field_name,
        'float' => TRUE,
        'click sortable' => TRUE,
        'additional fields' => [
          'latitude' => [
            'field' => $field_name . '_lat',
          ],
          'longitude' => [
            'field' => $field_name . '_lon',
          ],
        ],
      ],
      'argument' => [
        'id' => 'geofield_proximity_argument',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $field_name . '_proximity',
        'real field' => $field_name,
        'label' => t('Distance to !field_name', [$field_name]),
        'empty field name' => '- No value -',
        'additional fields' => [
          $field_name . 'value',
          $field_name . '_lat',
          $field_name . '_lon',
        ],
      ],
      'filter' => [
        'id' => 'geofield_proximity_filter',
        'real field' => $field_name,
      ],
      'sort' => [
        'id' => 'geofield_proximity_sort',
        'real field' => $field_name,
      ],
    ];

    $data[$table_name][$field_name . '_boundary'] = [
      'group' => $table_data[$field_name . '_value']['group'],
      'title' => t('@label Rectangular Boundary (@field_name)', [
        '@label' => $field_label,
        '@field_name' => $field_name,
      ]),
      'help' => $table_data[$field_name . '_value']['help'],
      'argument' => [
        'id' => 'geofield_rectangular_boundary_argument',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $field_name . '_boundary',
        'real field' => $field_name,
        'label' => t('Rectangular Boundaries around !field_name', [$field_name]),
        'empty field name' => '- No value -',
        'additional fields' => [
          $field_name . '_lat',
          $field_name . '_lon',
        ],
      ],
      'filter' => [
        'id' => 'geofield_rectangular_boundary_filter',
        'table' => $table_name,
        'entity_type' => $entity_type_id,
        'field_name' => $field_name . '_boundary',
        'real field' => $field_name,
        'label' => t('Rectangular Boundaries around !field_name', [$field_name]),
        'allow empty' => TRUE,
        'additional fields' => [
          $field_name . '_lat',
          $field_name . '_lon',
        ],
      ],
    ];

  }

  return $data;
}

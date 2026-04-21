<?php

/**
 * @file
 * Provide views data for geolocation.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function geolocation_field_views_data(FieldStorageConfigInterface $field_storage) {
  // Make sure views.views.inc is loaded.
  \Drupal::moduleHandler()->loadInclude('views', 'inc', 'views.views');

  $entity_storage = \Drupal::entityTypeManager()->getStorage($field_storage->getTargetEntityTypeId());
  $entity_definition = \Drupal::entityTypeManager()->getDefinition($field_storage->getTargetEntityTypeId());

  // Get the default data from the views module.
  $data = views_field_default_views_data($field_storage);

  $title_short = $help = '';

  if ($table_mapping = $entity_storage->getTableMapping()) {
    $field_table_name = $table_mapping->getFieldTableName($field_storage->getName());
    if (!empty($data[$field_table_name][$field_storage->getName()])) {
      $title_short = $data[$field_table_name][$field_storage->getName()]['title short'];
      $help = $data[$field_table_name][$field_storage->getName()]['help'];
    }
  }

  $args = ['@field_name' => $field_storage->getName()];

  // Loop through all of the results and set our overrides.
  foreach ($data as $table_name => $table_data) {
    $data[$table_name]['table']['entity type'] = $field_storage->getTargetEntityTypeId();
    foreach ($table_data as $field_name => $field_data) {
      // Only modify fields.
      if ($field_name != 'delta') {
        if (isset($field_data['field'])) {
          // Use our own field handler.
          $data[$table_name][$field_name]['field']['id'] = 'geolocation_field';
          $data[$table_name][$field_name]['field']['click sortable'] = FALSE;
        }
        if (isset($field_data['filter'])) {
          if (substr($field_name, -4, 4) == '_lat') {
            $data[$table_name][$field_name]['title'] = t('Latitude (@field_name)', $args);
            continue;
          }
          if (substr($field_name, -4, 4) == '_lng') {
            $data[$table_name][$field_name]['title'] = t('Longitude (@field_name)', $args);
            continue;
          }
          // The default filters are mostly not useful except lat/lng.
          unset($data[$table_name][$field_name]['filter']);
        }
        if (isset($field_data['argument'])) {
          // The default arguments aren't useful at all so remove them.
          unset($data[$table_name][$field_name]['argument']);
        }
        if (isset($field_data['sort'])) {
          // The default arguments aren't useful at all so remove them.
          unset($data[$table_name][$field_name]['sort']);
        }
      }
    }

    // Add proximity handlers.
    $data[$table_name][$args['@field_name'] . '_proximity'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Proximity (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Proximity"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_argument_proximity',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
      'filter' => [
        'id' => 'geolocation_filter_proximity',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
      'field' => [
        'table' => $table_name,
        'id' => 'geolocation_field_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'real field' => $args['@field_name'],
        'float' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
        'element type' => 'div',
        'is revision' => (isset($table_data[$args['@field_name']]['field']['is revision']) && $table_data[$args['@field_name']]['field']['is revision']),
        'click sortable' => TRUE,
      ],
      'sort' => [
        'table' => $table_name,
        'id' => 'geolocation_sort_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'real field' => $args['@field_name'],
      ],
    ];

    $data[$table_name][$args['@field_name'] . '_proximity_form'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Proximity Form Field (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Proximity Form"),
      'help' => $help,
      'field' => [
        'table' => $table_name,
        'id' => 'geolocation_field_proximity_form',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'real field' => $args['@field_name'],
        'float' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
        'element type' => 'div',
        'is revision' => (isset($table_data[$args['@field_name']]['field']['is revision']) && $table_data[$args['@field_name']]['field']['is revision']),
        'click sortable' => TRUE,
      ],
      'sort' => [
        'table' => $table_name,
        'id' => 'geolocation_sort_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'real field' => $args['@field_name'],
      ],
    ];

    $data[$table_name][$args['@field_name'] . '_entity_argument'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Proximity Entity Argument (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Entity Argument"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_entity_argument',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_entity_argument',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
    ];

    $data[$table_name][$args['@field_name'] . '_geocoder_argument'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Proximity Geocoder Address Argument (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Geocoder Address Argument"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_geocoder_argument',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_geocoder_argument',
        'real field' => $args['@field_name'],
        'label' => t('Distance to !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
          $args['@field_name'] . '_lat_sin',
          $args['@field_name'] . '_lat_cos',
          $args['@field_name'] . '_lng_rad',
        ],
      ],
    ];

    // Add boundary handlers.
    $data[$table_name][$args['@field_name'] . '_boundary'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Boundary (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Boundary"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_argument_boundary',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_boundary',
        'real field' => $args['@field_name'],
        'label' => t('Boundaries around !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
        ],
      ],
      'filter' => [
        'id' => 'geolocation_filter_boundary',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_boundary',
        'real field' => $args['@field_name'],
        'label' => t('Boundaries around !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_lat',
          $args['@field_name'] . '_lng',
        ],
      ],
    ];
  }

  return $data;
}

<?php

/**
 * @file
 * Provide views data for geolocation.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function geolocation_geometry_field_views_data(FieldStorageConfigInterface $field_storage) {

  $geometry_fields = [];
  foreach (
    [
      'geolocation',
      'geolocation_geometry_geometry',
      'geolocation_geometry_polygon',
      'geolocation_geometry_multipolygon',
      'geolocation_geometry_linestring',
      'geolocation_geometry_multilinestring',
      'geolocation_geometry_point',
      'geolocation_geometry_multipoint',
    ] as $field_type
  ) {
    foreach (\Drupal::service('entity_field.manager')->getFieldMapByFieldType($field_type) as $entity_type => $geometry_field_list) {
      /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $entity_storage */
      $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entity_definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
      $entity_table_mapping = $entity_storage->getTableMapping();
      $entity_label = (string) $entity_definition->getBundleLabel();
      foreach ($geometry_field_list as $geometry_field_name => $geometry_field_data) {
        $geometry_field_id = $entity_type . '__' . $geometry_field_name;
        $geometry_fields[$geometry_field_id] = $geometry_field_data;
        $geometry_fields[$geometry_field_id]['field_type'] = $field_type;
        $geometry_fields[$geometry_field_id]['entity_type'] = $entity_type;
        $geometry_fields[$geometry_field_id]['entity_id_field'] = $entity_definition->getKey('id');
        $geometry_fields[$geometry_field_id]['entity_data_table'] = $entity_table_mapping->getDataTable();
        $geometry_fields[$geometry_field_id]['entity_label'] = $entity_label;
        $geometry_fields[$geometry_field_id]['geometry_field_name'] = $geometry_field_name;
        $geometry_fields[$geometry_field_id]['geometry_field_table'] = $entity_table_mapping->getFieldTableName($geometry_field_name);
      }
    }
  }

  // Make sure views.views.inc is loaded.
  \Drupal::moduleHandler()->loadInclude('views', 'inc', 'views.views');

  $entity_definition = \Drupal::entityTypeManager()->getDefinition($field_storage->getTargetEntityTypeId());

  // Get the default data from the views module.
  $data = views_field_default_views_data($field_storage);

  $args = ['@field_name' => $field_storage->getName()];

  // Loop through all of the results and set our overrides.
  foreach ($data as $table_name => $table_data) {
    $data[$table_name]['table']['entity type'] = $field_storage->getTargetEntityTypeId();
    foreach ($table_data as $field_name => $field_data) {
      // Only modify fields.
      if ($field_name == 'delta') {
        continue;
      }
      if (isset($field_data['filter'])) {
        if (substr($field_name, -9, 9) == '_geometry') {
          $data[$table_name][$field_name]['title'] = t('Geometry (@field_name)', $args);
          continue;
        }
        if (substr($field_name, -4, 4) == '_wkt') {
          $data[$table_name][$field_name]['title'] = t('WKT/Well Known Text (@field_name)', $args);
          continue;
        }
        if (substr($field_name, -8, 8) == '_geojson') {
          $data[$table_name][$field_name]['title'] = t('GeoJSON (@field_name)', $args);
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

    $title_short = $help = '';
    // Add proximity handlers.
    $data[$table_name][$args['@field_name'] . '_proximity'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Geo Proximity (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Geo Proximity"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_geometry_argument_proximity',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Geo Distance to !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_geometry',
        ],
      ],
      'filter' => [
        'id' => 'geolocation_geometry_filter_proximity',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_proximity',
        'real field' => $args['@field_name'],
        'label' => t('Geo Distance to !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_geometry',
        ],
      ],
      'field' => [
        'table' => $table_name,
        'id' => 'geolocation_geometry_field_proximity',
        'field_name' => $args['@field_name'] . '_proximity',
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'real field' => $args['@field_name'],
        'float' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_geometry',
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

    // Add boundary handlers.
    $data[$table_name][$args['@field_name'] . '_boundary'] = [
      'group' => $entity_definition->getLabel(),
      'title' => t('Geo Boundary (@field_name)', $args),
      'title short' => $title_short . ' - ' . t("Boundary"),
      'help' => $help,
      'argument' => [
        'id' => 'geolocation_geometry_argument_boundary',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_boundary',
        'real field' => $args['@field_name'],
        'label' => t('Geo Boundaries around !field_name', $args),
        'empty field name' => '- No value -',
        'additional fields' => [
          $args['@field_name'] . '_geometry',
        ],
      ],
      'filter' => [
        'id' => 'geolocation_geometry_filter_boundary',
        'table' => $table_name,
        'entity_type' => $field_storage->getTargetEntityTypeId(),
        'field_name' => $args['@field_name'] . '_boundary',
        'real field' => $args['@field_name'],
        'label' => t('Geo Boundaries around !field_name', $args),
        'allow empty' => TRUE,
        'additional fields' => [
          $args['@field_name'] . '_geometry',
        ],
      ],
    ];

    foreach ($geometry_fields as $geometry_field_id => $geometry_field_data) {
      if ($field_storage->getName() == $geometry_field_data['geometry_field_name'] && $field_storage->getTargetEntityTypeId() == $geometry_field_data['entity_type']) {
        continue;
      }
      $data[$table_name][$geometry_field_id] = [
        'group' => t('Geolocation Geometry'),
        'title' => t('Geolocation Geometry'),
        'title short' => t('Geolocation Geometry'),
        'help' => '',
        'relationship' => [
          'title' => t('@entity_type by @source_field_name via @field_name geometry', [
            '@source_field_name' => $field_storage->getName(),
            '@entity_type' => $geometry_field_data['entity_label'],
            '@field_name' => $geometry_field_data['geometry_field_name'],
          ]),
          'label' => t('@entity_type by @source_field_name via @field_name geometry', [
            '@source_field_name' => $field_storage->getName(),
            '@entity_type' => $geometry_field_data['entity_label'],
            '@field_name' => $geometry_field_data['geometry_field_name'],
          ]),
          'group' => t('Geolocation Geometry'),
          'help' => '',
          'id' => 'geolocation_geometry',
          'field_type' => $field_storage->getType(),
          'table' => $table_name,
          'field' => $field_storage->getName(),
          'relationship field' => $geometry_field_data['geometry_field_name'],
          'relationship table' => $geometry_field_data['geometry_field_table'],
          'base' => $geometry_field_data['entity_data_table'],
          'base field' => $geometry_field_data['entity_id_field'],
        ],
      ];
    }
  }

  return $data;
}

/**
 * Implements hook_field_views_data_views_data_alter().
 */
function geolocation_geometry_field_views_data_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
  $entity_type = $field_storage->getTargetEntityTypeId();
  /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $entity_storage */
  $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
  $entity_definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
  $entity_table_mapping = $entity_storage->getTableMapping();
  $entity_label = (string) $entity_definition->getBundleLabel();

  $geometry_field_id = $entity_type . '__' . $field_storage->getName();
  $geometry_field_data = [
    'field_type' => $field_storage->getType(),
    'entity_type' => $entity_type,
    'entity_id_field' => $entity_definition->getKey('id'),
    'entity_data_table' => $entity_table_mapping->getDataTable(),
    'entity_label' => $entity_label,
    'geometry_field_name' => $field_storage->getName(),
    'geometry_field_table' => $entity_table_mapping->getFieldTableName($field_storage->getName()),
  ];

  foreach (\Drupal::service('entity_field.manager')->getFieldMapByFieldType('geolocation') as $entity_type => $geolocation_fields) {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $geolocation_table_mapping */
    $geolocation_table_mapping = \Drupal::entityTypeManager()->getStorage($entity_type)->getTableMapping();

    foreach ($geolocation_fields as $geolocation_field_name => $geolocation_field_data) {
      $geolocation_field_table_name = $geolocation_table_mapping->getFieldTableName($geolocation_field_name);
      if (empty($data[$geolocation_field_table_name])) {
        continue;
      }

      $data[$geolocation_field_table_name][$geometry_field_id] = [
        'group' => t('Geolocation Geometry'),
        'title' => t('Geolocation Geometry'),
        'title short' => t('Geolocation Geometry'),
        'help' => '',
        'relationship' => [
          'title' => t('@entity_type by @source_field_name via @field_name geometry', [
            '@source_field_name' => $geolocation_field_name,
            '@entity_type' => $geometry_field_data['entity_label'],
            '@field_name' => $geometry_field_data['geometry_field_name'],
          ]),
          'label' => t('@entity_type by @source_field_name via @field_name geometry', [
            '@source_field_name' => $geolocation_field_name,
            '@entity_type' => $geometry_field_data['entity_label'],
            '@field_name' => $geometry_field_data['geometry_field_name'],
          ]),
          'group' => t('Geolocation Geometry'),
          'help' => '',
          'id' => 'geolocation_geometry',
          'field_type' => 'geolocation',
          'table' => $geolocation_field_table_name,
          'field' => $geolocation_field_name,
          'relationship field' => $geometry_field_data['geometry_field_name'],
          'relationship table' => $geometry_field_data['geometry_field_table'],
          'base' => $geometry_field_data['entity_data_table'],
          'base field' => $geometry_field_data['entity_id_field'],
        ],
      ];
    }
  }

}

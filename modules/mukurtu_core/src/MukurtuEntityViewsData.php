<?php

namespace Drupal\mukurtu_core;

use Drupal\views\EntityViewsData;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * This class only exists because Drupal does not yet offer views integration
 * for fields defined via bundle classes bundleFieldDefinitions. It can be
 * removed once https://www.drupal.org/project/drupal/issues/2898635 has been
 * addressed. Functionality here is stock EntityViewsData with the patch from
 * https://www.drupal.org/files/issues/2023-08-23/2898635-60.patch backported
 * to our current version of Drupal 9.5.
 */
class MukurtuEntityViewsData extends EntityViewsData {

  /**
  * The entity bundle info.
  *
  * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
  */
  protected $entityTypeBundleInfo;

  /**
   * Constructs an EntityViewsData object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to provide views integration for.
   * @param \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage_controller
   *   The storage handler used for this entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeInterface $entity_type, SqlEntityStorageInterface $storage_controller, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, TranslationInterface $translation_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityType = $entity_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->storage = $storage_controller;
    $this->moduleHandler = $module_handler;
    $this->setStringTranslation($translation_manager);
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = [];

    $base_table = $this->entityType->getBaseTable() ?: $this->entityType->id();
    $views_revision_base_table = NULL;
    $revisionable = $this->entityType->isRevisionable();
    $entity_id_key = $this->entityType->getKey('id');
    $entity_keys = $this->entityType->getKeys();

    $revision_table = '';
    if ($revisionable) {
      $revision_table = $this->entityType->getRevisionTable() ?: $this->entityType->id() . '_revision';
    }

    $translatable = $this->entityType->isTranslatable();
    $data_table = '';
    if ($translatable) {
      $data_table = $this->entityType->getDataTable() ?: $this->entityType->id() . '_field_data';
    }

    // Some entity types do not have a revision data table defined, but still
    // have a revision table name set in
    // \Drupal\Core\Entity\Sql\SqlContentEntityStorage::initTableLayout() so we
    // apply the same kind of logic.
    $revision_data_table = '';
    if ($revisionable && $translatable) {
      $revision_data_table = $this->entityType->getRevisionDataTable() ?: $this->entityType->id() . '_field_revision';
    }
    $entity_revision_key = $this->entityType->getKey('revision');
    $revision_field = $entity_revision_key;

    // Setup base information of the views data.
    $data[$base_table]['table']['group'] = $this->entityType->getLabel();
    $data[$base_table]['table']['provider'] = $this->entityType->getProvider();

    $views_base_table = $base_table;
    if ($data_table) {
      $views_base_table = $data_table;
    }
    $data[$views_base_table]['table']['base'] = [
      'field' => $entity_id_key,
      'title' => $this->entityType->getLabel(),
      'cache_contexts' => $this->entityType->getListCacheContexts(),
      'access query tag' => $this->entityType->id() . '_access',
    ];
    $data[$base_table]['table']['entity revision'] = FALSE;

    if ($label_key = $this->entityType->getKey('label')) {
      if ($data_table) {
        $data[$views_base_table]['table']['base']['defaults'] = [
          'field' => $label_key,
          'table' => $data_table,
        ];
      } else {
        $data[$views_base_table]['table']['base']['defaults'] = [
          'field' => $label_key,
        ];
      }
    }

    // Entity types must implement a list_builder in order to use Views'
    // entity operations field.
    if ($this->entityType->hasListBuilderClass()) {
      $data[$base_table]['operations'] = [
        'field' => [
          'title' => $this->t('Operations links'),
          'help' => $this->t('Provides links to perform entity operations.'),
          'id' => 'entity_operations',
        ],
      ];
      if ($revision_table) {
        $data[$revision_table]['operations'] = [
          'field' => [
            'title' => $this->t('Operations links'),
            'help' => $this->t('Provides links to perform entity operations.'),
            'id' => 'entity_operations',
          ],
        ];
      }
    }

    if ($this->entityType->hasViewBuilderClass()) {
      $data[$base_table]['rendered_entity'] = [
        'field' => [
          'title' => $this->t('Rendered entity'),
          'help' => $this->t('Renders an entity in a view mode.'),
          'id' => 'rendered_entity',
        ],
      ];
    }

    // Setup relations to the revisions/property data.
    if ($data_table) {
      $data[$base_table]['table']['join'][$data_table] = [
        'left_field' => $entity_id_key,
        'field' => $entity_id_key,
        'type' => 'INNER',
      ];
      $data[$data_table]['table']['group'] = $this->entityType->getLabel();
      $data[$data_table]['table']['provider'] = $this->entityType->getProvider();
      $data[$data_table]['table']['entity revision'] = FALSE;
    }
    if ($revision_table) {
      $data[$revision_table]['table']['group'] = $this->t('@entity_type revision', ['@entity_type' => $this->entityType->getLabel()]);
      $data[$revision_table]['table']['provider'] = $this->entityType->getProvider();

      $views_revision_base_table = $revision_table;
      if ($revision_data_table) {
        $views_revision_base_table = $revision_data_table;
      }
      $data[$views_revision_base_table]['table']['entity revision'] = TRUE;
      $data[$views_revision_base_table]['table']['base'] = [
        'field' => $revision_field,
        'title' => $this->t('@entity_type revisions', ['@entity_type' => $this->entityType->getLabel()]),
      ];
      // Join the revision table to the base table.
      $data[$views_revision_base_table]['table']['join'][$views_base_table] = [
        'left_field' => $revision_field,
        'field' => $revision_field,
        'type' => 'INNER',
      ];

      if ($revision_data_table) {
        $data[$revision_data_table]['table']['group'] = $this->t('@entity_type revision', ['@entity_type' => $this->entityType->getLabel()]);
        $data[$revision_data_table]['table']['entity revision'] = TRUE;

        $data[$revision_table]['table']['join'][$revision_data_table] = [
          'left_field' => $revision_field,
          'field' => $revision_field,
          'type' => 'INNER',
        ];
      }

      // Add a filter for showing only the latest revisions of an entity.
      $data[$revision_table]['latest_revision'] = [
        'title' => $this->t('Is Latest Revision'),
        'help' => $this->t('Restrict the view to only revisions that are the latest revision of their entity.'),
        'filter' => ['id' => 'latest_revision'],
      ];
      if ($this->entityType->isTranslatable()) {
        $data[$revision_table]['latest_translation_affected_revision'] = [
          'title' => $this->t('Is Latest Translation Affected Revision'),
          'help' => $this->t('Restrict the view to only revisions that are the latest translation affected revision of their entity.'),
          'filter' => ['id' => 'latest_translation_affected_revision'],
        ];
      }
      // Add a relationship from the revision table back to the main table.
      $entity_type_label = $this->entityType->getLabel();
      $data[$views_revision_base_table][$entity_id_key]['relationship'] = [
        'id' => 'standard',
        'base' => $views_base_table,
        'base field' => $entity_id_key,
        'title' => $entity_type_label,
        'help' => $this->t('Get the actual @label from a @label revision', ['@label' => $entity_type_label]),
      ];
      $data[$views_revision_base_table][$entity_revision_key]['relationship'] = [
        'id' => 'standard',
        'base' => $views_base_table,
        'base field' => $entity_revision_key,
        'title' => $this->t('@label revision', ['@label' => $entity_type_label]),
        'help' => $this->t('Get the actual @label from a @label revision', ['@label' => $entity_type_label]),
      ];
      if ($translatable) {
        $extra = [
          'field' => $entity_keys['langcode'],
          'left_field' => $entity_keys['langcode'],
        ];
        $data[$views_revision_base_table][$entity_id_key]['relationship']['extra'][] = $extra;
        $data[$views_revision_base_table][$entity_revision_key]['relationship']['extra'][] = $extra;
        $data[$revision_table]['table']['join'][$views_base_table]['left_field'] = $entity_revision_key;
        $data[$revision_table]['table']['join'][$views_base_table]['field'] = $entity_revision_key;
      }

    }

    $this->addEntityLinks($data[$base_table]);
    if ($views_revision_base_table) {
      $this->addEntityLinks($data[$views_revision_base_table]);
    }

    // Load all typed data definitions of all fields. This should cover each of
    // the entity base, revision, data tables.
    $field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entityType->id());

    $field_storage_definitions = array_map(function (FieldDefinitionInterface $definition) {
      return $definition->getFieldStorageDefinition();
    }, $field_definitions);

    // Add any bundle fields defined in code.
    if ($this->entityType->hasKey('bundle')) {
      $bundle_field_definitions = [];
      foreach ($this->entityTypeBundleInfo->getBundleInfo($this->entityType->id()) as $bundle_id => $bundle_info) {
        $bundle_field_definitions += $this->entityFieldManager->getFieldDefinitions($this->entityType->id(), $bundle_id);
      }

      $field_definitions += $bundle_field_definitions;
    }

    $field_storage_definitions = array_map(function (FieldDefinitionInterface $definition) {
      return $definition->getFieldStorageDefinition();
    }, $field_definitions);

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $this->storage->getTableMapping($field_storage_definitions);
    if ($table_mapping) {
      // Fetch all fields that can appear in both the base table and the data
      // table.
      $duplicate_fields = array_intersect_key($entity_keys, array_flip(['id', 'revision', 'bundle']));
      // Iterate over each table we have so far and collect field data for each.
      // Based on whether the field is in the field_definitions provided by the
      // entity field manager.
      // @todo We should better just rely on information coming from the entity
      //   storage.
      // @todo https://www.drupal.org/node/2337511
      foreach ($table_mapping->getTableNames() as $table) {
        foreach ($table_mapping->getFieldNames($table) as $field_name) {
          // To avoid confusing duplication in the user interface, for fields
          // that are on both base and data tables, only add them on the data
          // table (same for revision vs. revision data).
          if ($data_table && ($table === $base_table || $table === $revision_table) && in_array($field_name, $duplicate_fields)) {
            continue;
          }

          if (isset($this->getFieldStorageDefinitions()[$field_name])) {
            $this->mapFieldDefinition($table, $field_name, $field_definitions[$field_name], $table_mapping, $data[$table]);
          }
        }
      }

      foreach ($field_storage_definitions as $field_storage_definition) {
        if ($table_mapping->requiresDedicatedTableStorage($field_storage_definition)) {
          $table = $table_mapping->getDedicatedDataTableName($field_storage_definition);

          $data[$table]['table']['group'] = $this->entityType->getLabel();
          $data[$table]['table']['provider'] = $this->entityType->getProvider();
          $data[$table]['table']['join'][$views_base_table] = [
            'left_field' => $entity_id_key,
            'field' => 'entity_id',
            'extra' => [
              ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
            ],
          ];

          if ($revisionable) {
            $revision_table = $table_mapping->getDedicatedRevisionTableName($field_storage_definition);

            $data[$revision_table]['table']['group'] = $this->t('@entity_type revision', ['@entity_type' => $this->entityType->getLabel()]);
            $data[$revision_table]['table']['provider'] = $this->entityType->getProvider();
            $data[$revision_table]['table']['join'][$views_revision_base_table] = [
              'left_field' => $revision_field,
              'field' => 'entity_id',
              'extra' => [
                ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
              ],
            ];
          }
        }
      }
      if (($uid_key = $entity_keys['uid'] ?? '')) {
        $data[$data_table][$uid_key]['filter']['id'] = 'user_name';
      }
      if ($revision_table && ($revision_uid_key = $this->entityType->getRevisionMetadataKeys()['revision_user'] ?? '')) {
        $data[$revision_table][$revision_uid_key]['filter']['id'] = 'user_name';
      }
    }

    // Add the entity type key to each table generated.
    $entity_type_id = $this->entityType->id();
    array_walk($data, function (&$table_data) use ($entity_type_id) {
      $table_data['table']['entity type'] = $entity_type_id;
    });

    return $data;
  }

}

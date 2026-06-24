<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

class CsvExporterFormBase extends EntityForm {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  public function __construct(EntityStorageInterface $entity_storage, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityStorage = $entity_storage;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('entity_type.manager')->getStorage('csv_exporter'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->getDescription(),
    ];

    $form['site_wide'] = [
      '#type' => 'radios',
      '#title' => $this->t('Visibility'),
      '#default_value' => (int) $entity->isSiteWide(),
      '#options' => [
        0 => $this->t('Only me'),
        1 => $this->t('All export users'),
      ],
    ];

    $form['relationships'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Configuration'),
    ];


    $form['relationships']['field_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the identifier format to export'),
      '#description' => $this->t('Controls how references to other entities are written in exported cells (e.g., a taxonomy term reference or a related content field). This does not affect the exported entity\'s own ID or UUID columns, which are controlled per entity type in the Field Mappings section.'),
      '#default_value' => $entity->getIdFieldSetting(),
      '#options' => [
        'id' => $this->t('Export the ID'),
        'uuid' => $this->t('Export the UUID'),
      ],
      'id' => ['#description' => $this->t("Suitable for importing back into the same site.")],
      'uuid' => ['#description' => $this->t("Preferable when sharing data for use on other sites.")],
    ];

    $form['relationships']['entity_reference_node'] = [
      '#type' => 'radios',
      '#title' => $this->t('Referenced content'),
      '#default_value' => $entity->getEntityReferenceSetting('node'),
      '#options' => [
        'id' => $this->t('Export the identifier (node ID or UUID)'),
        'entity_shallow' => $this->t('Export the identifier and include one level of referenced content'),
        'entity' => $this->t('Export the identifier and include referenced content recursively'),
      ],
      'id' => ['#description' => $this->t('Only the ID or UUID is written to the cell. The referenced content is not included in the export.')],
      'entity_shallow' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced content is also exported. References within referenced content are not followed.')],
      'entity' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced content is also exported. References within referenced content are also followed, recursively. This may result in very large exports, use with caution.')],
    ];

    $form['relationships']['entity_reference_media'] = [
      '#type' => 'radios',
      '#title' => $this->t('Media'),
      '#default_value' => $entity->getEntityReferenceSetting('media'),
      '#options' => [
        'id' => $this->t('Export the identifier (media ID or UUID)'),
        'entity_shallow' => $this->t('Export the identifier and include one level of referenced media'),
        'entity' => $this->t('Export the identifier and include referenced media recursively'),
      ],
      'id' => ['#description' => $this->t('Only the ID or UUID is written to the cell. The referenced media is not included in the export.')],
      'entity_shallow' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced media is also exported.')],
      'entity' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced media is also exported. This may result in very large exports, use with caution.')],
    ];

    $form['relationships']['media_asset_packaging'] = [
      '#type' => 'radios',
      '#title' => $this->t('Media asset packaging'),
      '#description' => $this->t('Controls how binary files (audio, video, documents, images, and thumbnails) are included when media entities are exported. Only applies when Media above is set to include the referenced media.'),
      '#default_value' => $entity->getFileFieldSetting(),
      '#options' => [
        'id' => $this->t('Export the identifier only (no binary files)'),
        'path_with_binary' => $this->t('Package the binary files and export the relative path'),
        'file_entity' => $this->t('Package the binary files and export the referenced file entity'),
      ],
      'id' => ['#description' => $this->t('Suitable when the files already exist on the destination site.')],
      'path_with_binary' => ['#description' => $this->t('Bundles all binary files into the export archive. Use this when moving content to another site or making a portable backup.')],
      'file_entity' => ['#description' => $this->t('Bundles all binary files and also exports each file entity as a structured row. Use this for full round-trip imports where file metadata must also be imported.')],
    ];

    $form['relationships']['entity_reference_taxonomy_term'] = [
      '#type' => 'radios',
      '#title' => $this->t('Taxonomy Terms'),
      '#default_value' => $entity->getEntityReferenceSetting('taxonomy_term'),
      '#options' => [
        'id' => $this->t('Export the identifier (term ID or UUID)'),
        'name' => $this->t('Export the term label'),
        'entity_shallow' => $this->t('Export the identifier and include one level of referenced terms'),
        'entity' => $this->t('Export the identifier and include referenced terms recursively'),
      ],
      'id' => ['#description' => $this->t('Only the ID or UUID is written to the cell. The referenced term is not included in the export.')],
      'name' => ['#description' => $this->t('The term\'s label is written to the cell. Useful when sharing data across sites where IDs may differ.')],
      'entity_shallow' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced term is also exported.')],
      'entity' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced term is also exported. This may result in very large exports, use with caution.')],
    ];

    $form['relationships']['entity_reference_user'] = [
      '#type' => 'radios',
      '#title' => $this->t('Users'),
      '#default_value' => $entity->getEntityReferenceSetting('user'),
      '#options' => [
        'id' => $this->t('Export the identifier (user ID or UUID)'),
        'username' => $this->t('Export the username'),
      ],
      'id' => ['#description' => $this->t('Only the ID or UUID is written to the cell.')],
      'username' => ['#description' => $this->t('The account name (machine name) is written to the cell. More portable across sites than a numeric ID.')],
    ];

    $form['relationships']['entity_reference_paragraph'] = [
      '#type' => 'radios',
      '#title' => $this->t('Paragraphs'),
      '#default_value' => $entity->getEntityReferenceSetting('paragraph'),
      '#options' => [
        'id' => $this->t('Export the identifier (paragraph ID or UUID)'),
        'entity_shallow' => $this->t('Export the identifier and include one level of referenced paragraphs'),
        'entity' => $this->t('Export the identifier and include referenced paragraphs recursively'),
      ],
      'id' => ['#description' => $this->t('Only the ID or UUID is written to the cell. The referenced paragraph is not included in the export.')],
      'entity_shallow' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced paragraph is also exported. References within referenced paragraphs are not followed.')],
      'entity' => ['#description' => $this->t('The ID or UUID is written to the cell, and the referenced paragraph is also exported. References within referenced paragraphs are also followed, recursively. This may result in very large exports, use with caution.')],
    ];

    $form['csv'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t("CSV File Format Settings")
    ];

    $form['csv']['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field delimiter'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getSeparator(),
      '#required' => TRUE,
    ];

    $form['csv']['enclosure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field enclosure'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getEnclosure(),
      '#required' => TRUE,
    ];

    $form['csv']['escape'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Escape character'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getEscape(),
      '#required' => FALSE,
    ];

    /*     // Only in PHP 8.1
        $form['csv']['eol'] = [
          '#type' => 'textfield',
          '#title' => $this->t('End of line sequence'),
          '#maxlength' => 255,
          '#size' => 5,
          '#default_value' => $entity->getEol(),
          '#required' => TRUE,
        ]; */

    $form['csv']['multivalue_delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Multi-value delimiter'),
      '#maxlength' => 255,
      '#size' => 5,
      '#default_value' => $entity->getMultivalueDelimiter(),
      '#required' => TRUE,
    ];

    $form['csv']['local_contexts_delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local Contexts delimiter'),
      '#maxlength' => 10,
      '#size' => 5,
      '#default_value' => $entity->getLocalContextsDelimiter(),
      '#required' => TRUE,
    ];

    $format_options = [];
    foreach (filter_formats() as $format_id => $format) {
      $format_options[$format_id] = $format->label();
    }
    $form['csv']['default_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Default text format'),
      '#options' => $format_options,
      '#default_value' => $entity->getDefaultFormat() ?? 'basic_html',
      '#description' => $this->t('The text format the exported content is in. Set the matching import template to the same value.'),
    ];

    $form += $this->buildEntityFieldMapping();

    return $form;
  }

  protected function buildEntityFieldMapping() {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $build = [];
    $other_build = [];
    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();
    $handled = [];

    // Secondary bundles go into "Other Content Types". NULL = all bundles.
    $secondary_bundles = [
      'node' => ['article', 'page', 'landing_page'],
      'paragraph' => ['footer_logo', 'footer_social_link'],
      'file' => NULL,
    ];

    // Custom groups interleave content types with their related paragraphs.
    // The first item's fields are placed directly in the group (no wrapper);
    // subsequent items each get a collapsed sub-details.
    $groups = [
      'digital_heritage' => [
        'label' => $this->t('Digital Heritage'),
        'items' => [
          ['type' => 'node', 'bundle' => 'digital_heritage'],
          ['type' => 'paragraph', 'bundle' => 'indigenous_knowledge_keepers'],
        ],
      ],
      'dictionary_word' => [
        'label' => $this->t('Dictionary Word'),
        'items' => [
          ['type' => 'node', 'bundle' => 'dictionary_word'],
          ['type' => 'paragraph', 'bundle' => 'dictionary_word_entry'],
          ['type' => 'paragraph', 'bundle' => 'sample_sentence'],
        ],
      ],
      'person' => [
        'label' => $this->t('Person'),
        'items' => [
          ['type' => 'node', 'bundle' => 'person'],
          ['type' => 'paragraph', 'bundle' => 'formatted_text_with_title'],
          ['type' => 'paragraph', 'bundle' => 'related_person'],
        ],
      ],
      'place' => [
        'label' => $this->t('Place'),
        'items' => [
          ['type' => 'node', 'bundle' => 'place'],
          ['type' => 'paragraph', 'bundle' => 'text_section_with_title'],
        ],
      ],
      'collection' => [
        'label' => $this->t('Collection'),
        'items' => [
          ['type' => 'node', 'bundle' => 'collection'],
        ],
      ],
      'word_list' => [
        'label' => $this->t('Word List'),
        'items' => [
          ['type' => 'node', 'bundle' => 'word_list'],
        ],
      ],
    ];

    foreach ($groups as $group_key => $group) {
      $single_item = count($group['items']) === 1;
      $build[$group_key] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $group['label'],
      ];
      foreach ($group['items'] as $i => $item) {
        ['type' => $type, 'bundle' => $bundle] = $item;
        $bundle_info = $all_bundle_info[$type][$bundle] ?? ['label' => $bundle];
        // First item's table sits directly in the group; subsequent items are
        // wrapped in a sub-details using the bundle label.
        $this->addBundleTable($build[$group_key], $entity, $type, $bundle, $bundle_info, $i === 0);
        $handled["{$type}__{$bundle}"] = TRUE;
      }
    }

    // Render remaining bundles by entity type, skipping handled and secondary.
    foreach ($entity->getSupportedEntityTypes() as $type) {
      $entity_type_obj = $this->entityTypeManager->getStorage($type)->getEntityType();
      $all_bundles = $all_bundle_info[$type];
      $single_bundle_type = count($all_bundles) === 1;

      $secondary_list = array_key_exists($type, $secondary_bundles)
        ? ($secondary_bundles[$type] ?? array_keys($all_bundles))
        : [];

      foreach ($all_bundles as $bundle => $bundle_info) {
        if (isset($handled["{$type}__{$bundle}"])) {
          continue;
        }

        if (in_array($bundle, $secondary_list)) {
          $other_build[$type] = $other_build[$type] ?? [
            '#type' => 'details',
            '#open' => FALSE,
            '#title' => $entity_type_obj->getLabel(),
          ];
          $this->addBundleTable($other_build[$type], $entity, $type, $bundle, $bundle_info, $single_bundle_type);
        }
        else {
          $build[$type] = $build[$type] ?? [
            '#type' => 'details',
            '#open' => FALSE,
            '#title' => $entity_type_obj->getLabel(),
          ];
          $this->addBundleTable($build[$type], $entity, $type, $bundle, $bundle_info, $single_bundle_type);
        }
      }
    }

    if (!empty($other_build)) {
      $build['other_content_types'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('Other Content Types'),
      ] + $other_build;
    }

    return $build;
  }

  protected function addBundleTable(array &$parent, $entity, string $type, string $bundle, array $bundle_info, bool $single_bundle_type) {
    $table_key = "{$type}__{$bundle}";
    $field_table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Export'),
        $this->t('Field name'),
        $this->t('Field label'),
        $this->t('CSV header label'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    foreach ($entity->getMappedFields($type, $bundle) as $weight => $mapped_field) {
      // Exclude 'behavior_settings' paragraph base field from the options.
      if ($type == 'paragraph' && $mapped_field['field_name'] == 'behavior_settings') {
        continue;
      }

      $row = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => 0,
      ];
      $row['export'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Export @field', ['@field' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $mapped_field['export'],
      ];
      $row['field_name'] = [
        '#type' => 'item',
        '#markup' => $mapped_field['field_name'],
      ];
      $row['field_label'] = [
        '#type' => 'item',
        '#markup' => $mapped_field['field_label'],
      ];
      $row['csv_header_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSV header label for @field', ['@field' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $mapped_field['csv_header_label'],
      ];
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
      $field_table[$mapped_field['field_name']] = $row;
    }

    if ($single_bundle_type) {
      $parent[$table_key] = $field_table;
    }
    else {
      $parent[$bundle] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $bundle_info['label'],
        $table_key => $field_table,
      ];
    }
  }

  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    $query = $this->entityStorage->getQuery();

    $result = $query
      ->condition('id', $element['#field_prefix'] . $entity_id)
      ->accessCheck(FALSE)
      ->execute();

    return (bool) $result;
  }

  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->getEntity();
    $values = $form_state->getValues();

    // Field mappings.
    $field_list = [];
    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($entity->getSupportedEntityTypes() as $type) {
      foreach($all_bundle_info[$type] as $bundle => $bundle_info) {
        $key = "{$type}__{$bundle}";
        $entity_type_field_mapping = [];
        if (isset($values[$key])) {
          foreach ($values[$key] as $fieldname => $field_values) {
            if ($field_values['export'] == "1") {
              $entity_type_field_mapping[$fieldname] = $field_values['csv_header_label'];
            }
          }
        }
        $field_list[$key] = $entity_type_field_mapping;
      }
    }

    $entity->setSiteWide((bool) $form_state->getValue('site_wide'));
    $entity->setMultivalueDelimiter($form_state->getValue('multivalue_delimiter'));
    $entity->setLocalContextsDelimiter($form_state->getValue('local_contexts_delimiter'));
    $entity->setDefaultFormat($form_state->getValue('default_format'));
    $mediaAssetPackaging = $form_state->getValue('media_asset_packaging');
    $entity->setFileFieldSetting($mediaAssetPackaging);
    $entity->setImageFieldSetting($mediaAssetPackaging);
    $entity->set('entity_fields_export_list', $field_list);
    $status = $entity->save();
    $form_state->setRedirect('mukurtu_export.export_settings');
  }

}

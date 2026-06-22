<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\mukurtu_export\AdHocExporterSource;
use Drupal\mukurtu_export\BatchExportExecutable;
use Drupal\mukurtu_export\ExportChildResolver;
use Drupal\mukurtu_export\Form\ExportBaseForm;
use Drupal\mukurtu_export\MukurtuExporterPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Export Plugin Configuration Form.
 */
class ExportSettingsForm extends ExportBaseForm {

  protected ExportChildResolver $childResolver;

  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    MukurtuExporterPluginManager $mukurtu_exporter_plugin_manager,
    ExportChildResolver $child_resolver,
  ) {
    parent::__construct($temp_store_factory, $entity_type_manager, $entity_field_manager, $entity_bundle_info, $mukurtu_exporter_plugin_manager);
    $this->childResolver = $child_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.mukurtu_exporter'),
      $container->get('mukurtu_export.child_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $adHocItems = $this->store->get('ad_hoc_items');

    if (!empty($adHocItems)) {
      // Ad-hoc mode: hide the list selector and show what is being exported.
      $form['export_list_id'] = [
        '#type' => 'value',
        '#value' => '',
      ];

      $labels = [];
      foreach ($adHocItems as $entity_type => $ids) {
        $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
        foreach ($entities as $entity) {
          $labels[] = $entity->label();
        }
      }
      $count = count($labels);
      $form['adhoc_summary'] = [
        '#theme' => 'item_list',
        '#title' => $this->formatPlural($count, 'Exporting 1 selected item', 'Exporting @count selected items'),
        '#items' => $labels,
        '#weight' => -10,
      ];

      // Show "Include child items" checkbox when the selection contains
      // collections or word lists with resolvable children.
      $child_count = 0;
      $recursive_additional = 0;
      foreach ($adHocItems['node'] ?? [] as $node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
        if (!$node) {
          continue;
        }
        if (in_array($node->bundle(), ['collection', 'word_list'])) {
          $direct = array_sum(array_map('count', $this->childResolver->getChildEntities($node)));
          $child_count += $direct;
          if ($node->bundle() === 'collection') {
            $recursive = array_sum(array_map('count', $this->childResolver->getChildEntitiesRecursive($node)));
            $recursive_additional += ($recursive - $direct);
          }
        }
      }
      if ($child_count > 0) {
        $form['include_children'] = [
          '#type' => 'checkbox',
          '#title' => $this->formatPlural(
            $child_count,
            'Also include 1 child item from collections and word lists in this selection',
            'Also include @count child items from collections and word lists in this selection',
          ),
          '#default_value' => FALSE,
          '#weight' => -5,
        ];

        if ($recursive_additional > 0) {
          $form['include_children_recursive'] = [
            '#type' => 'checkbox',
            '#title' => $this->formatPlural(
              $recursive_additional,
              'Also include all items nested within child collections in this selection (1 additional item)',
              'Also include all items nested within child collections in this selection (@count additional items)',
            ),
            '#default_value' => FALSE,
            '#weight' => -4,
            '#states' => [
              'visible' => [':input[name="include_children"]' => ['checked' => TRUE]],
            ],
          ];
        }
      }

      // Build per-node CR and MPI selection sections.
      $this->buildAdHocCrMpiElements($form, $form_state, $adHocItems['node'] ?? []);
    }
    else {
      $uid = \Drupal::currentUser()->id();
      $storage = $this->entityTypeManager->getStorage('export_list');
      $query = $storage->getQuery()->accessCheck(TRUE);
      $or = $query->orConditionGroup()
        ->condition('uid', $uid)
        ->condition('site_wide', TRUE);
      $ids = $query->condition($or)->sort('label')->execute();
      $lists = $storage->loadMultiple($ids);

      $options = [];
      foreach ($lists as $list) {
        $options[$list->id()] = $list->label();
      }

      $form['export_list_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Export list'),
        '#options' => $options,
        '#empty_option' => $this->t('- Select export list -'),
        '#empty_value' => '',
        '#default_value' => $this->getActiveExportListId() ?? '',
        '#description' => $this->t('Choose a saved export list.'),
        '#weight' => -10,
      ];
    }

    $settings = $this->getExporterConfig()['settings'] ?? [];
    $form += $this->exporter->settingsForm($form, $form_state, $settings);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::submitBack'],
      '#gin_action_item' => TRUE,
    ];
    if (!empty($adHocItems)) {
      $form['actions']['clear_selection'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear Selection'),
        '#submit' => ['::submitClearSelection'],
        '#limit_validation_errors' => [],
        '#gin_action_item' => TRUE,
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Export'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * Adds CR and MPI selection elements for the ad-hoc node list.
   *
   * Builds a fieldset per original record with a three-way radio, a fieldset
   * per community record with an OR checkbox, and a fieldset per MPI page with
   * a pre-checked page list. Stores resolved entities in form_state for submit.
   */
  protected function buildAdHocCrMpiElements(array &$form, FormStateInterface $form_state, array $node_ids): void {
    $or_data = [];
    $cr_data = [];
    $mpi_data = [];

    foreach ($node_ids as $node_id) {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
      if (!$node) {
        continue;
      }

      $community_records = $this->childResolver->getAccessibleCommunityRecords($node);
      if (!empty($community_records)) {
        $or_data[$node_id] = ['node' => $node, 'crs' => $community_records];
      }

      $original_record = $this->childResolver->getOriginalRecord($node);
      if ($original_record) {
        $cr_data[$node_id] = ['node' => $node, 'or' => $original_record];
      }

      $mpi_pages = $this->childResolver->getMultipagePages($node);
      if (!empty($mpi_pages)) {
        // Key by first page nid to deduplicate when multiple pages of the same
        // MPI appear in the selection.
        $first_id = array_key_first($mpi_pages);
        if (!isset($mpi_data[$first_id])) {
          $mpi = $this->childResolver->getMultipageEntity($node);
          $mpi_data[$first_id] = [
            'pages'  => $mpi_pages,
            'mpi_id' => $mpi ? (int) $mpi->id() : NULL,
          ];
        }
      }
    }

    $form_state->set('adhoc_or_data', $or_data);
    $form_state->set('adhoc_cr_data', $cr_data);
    $form_state->set('adhoc_mpi_data', $mpi_data);

    // Original record sections.
    foreach ($or_data as $node_id => ['node' => $node, 'crs' => $community_records]) {
      $key = 'cr_mode_' . $node_id;
      $select_key = 'cr_select_' . $node_id;
      $cr_options = [];
      foreach ($community_records as $cr) {
        $cr_options[$cr->id()] = $this->getCommunityRecordLabel($cr);
      }
      $form[$key] = [
        '#type' => 'radios',
        '#title' => $this->t('Community records for <em>@title</em>', ['@title' => $node->label()]),
        '#options' => [
          'none' => $this->t('Just this record'),
          'all' => $this->t('This record and all accessible community records'),
          'select' => $this->t('This record and select community records'),
        ],
        '#default_value' => 'none',
        '#weight' => -4,
      ];
      $form[$select_key] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select community records'),
        '#options' => $cr_options,
        '#default_value' => array_keys($cr_options),
        '#states' => [
          'visible' => [
            ':input[name="' . $key . '"]' => ['value' => 'select'],
          ],
        ],
        '#weight' => -4,
      ];
    }

    // Community record sections.
    foreach ($cr_data as $node_id => ['node' => $node, 'or' => $original_record]) {
      $key = 'include_or_' . $node_id;
      $form[$key] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Also include the original record: @title', ['@title' => $original_record->label()]),
        '#default_value' => FALSE,
        '#weight' => -4,
      ];
    }

    // Multipage item sections.
    foreach ($mpi_data as $first_id => ['pages' => $pages]) {
      $key = 'mpi_pages_' . $first_id;
      $page_options = [];
      foreach ($pages as $page) {
        $page_options[$page->id()] = $page->label();
      }
      $form[$key] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Pages in this multipage item'),
        '#options' => $page_options,
        '#default_value' => array_keys($page_options),
        '#weight' => -4,
      ];
    }
  }

  /**
   * Submit handler for "Duplicate Settings".
   */
  public function submitDuplicateSettings(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    if ($id = $this->exporter->duplicateSettings($form, $form_state)) {
      $settings = $this->exporter->getSettings($form, $form_state);
      $this->exporter->setConfiguration(['settings' => $settings]);
      $this->setExporterConfig($this->exporter->getConfiguration());
      $form_state->setRedirect('entity.csv_exporter.edit_form', ['csv_exporter' => $id]);
    }
  }

  /**
   * Submit handler for "Clear Selection" - removes ad-hoc items and reloads.
   */
  public function submitClearSelection(array &$form, FormStateInterface $form_state) {
    $this->store->delete('ad_hoc_items');
    $form_state->setRedirect('mukurtu_export.export_settings');
  }

  /**
   * Submit handler for the back button.
   */
  public function submitBack(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    $form_state->setRedirect('entity.export_list.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    $settings = $this->exporter->getSettings($form, $form_state);
    $this->exporter->setConfiguration(['settings' => $settings]);
    $this->setExporterConfig($this->exporter->getConfiguration());

    $ad_hoc_items = $this->store->get('ad_hoc_items') ?? [];

    // Expand the selection to include children of collections/word lists.
    if ($form_state->getValue('include_children_recursive')) {
      foreach ($ad_hoc_items['node'] ?? [] as $node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
        if (!$node) {
          continue;
        }
        foreach ($this->childResolver->getChildEntitiesRecursive($node) as $child_type => $child_ids) {
          $ad_hoc_items[$child_type] = ($ad_hoc_items[$child_type] ?? []) + $child_ids;
        }
      }
      $this->source = new AdHocExporterSource($ad_hoc_items);
      $this->executable = new BatchExportExecutable($this->source, $this->exporter);
    }
    elseif ($form_state->getValue('include_children')) {
      foreach ($ad_hoc_items['node'] ?? [] as $node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
        if (!$node) {
          continue;
        }
        foreach ($this->childResolver->getChildEntities($node) as $child_type => $child_ids) {
          $ad_hoc_items[$child_type] = ($ad_hoc_items[$child_type] ?? []) + $child_ids;
        }
      }
    }

    // Expand for community record selections on original records.
    foreach ($form_state->get('adhoc_or_data') ?? [] as $node_id => ['node' => $node, 'crs' => $community_records]) {
      $cr_mode = $form_state->getValue('cr_mode_' . $node_id);
      if ($cr_mode === 'all') {
        foreach ($community_records as $cr) {
          $id = (int) $cr->id();
          $ad_hoc_items['node'][$id] = $id;
        }
      }
      elseif ($cr_mode === 'select') {
        foreach ($form_state->getValue('cr_select_' . $node_id) ?? [] as $nid => $checked) {
          if ($checked) {
            $nid = (int) $nid;
            $ad_hoc_items['node'][$nid] = $nid;
          }
        }
      }
    }

    // Expand for OR inclusions from community records.
    foreach ($form_state->get('adhoc_cr_data') ?? [] as $node_id => ['or' => $original_record]) {
      if ($form_state->getValue('include_or_' . $node_id)) {
        $id = (int) $original_record->id();
        $ad_hoc_items['node'][$id] = $id;
      }
    }

    // Expand for multipage item page selections.
    foreach ($form_state->get('adhoc_mpi_data') ?? [] as $first_id => $mpi_entry) {
      $pages_selected = FALSE;
      foreach ($form_state->getValue('mpi_pages_' . $first_id) ?? [] as $nid => $checked) {
        if ($checked) {
          $nid = (int) $nid;
          $ad_hoc_items['node'][$nid] = $nid;
          $pages_selected = TRUE;
        }
      }
      // Also include the parent multipage_item entity when any pages are selected.
      if ($pages_selected && !empty($mpi_entry['mpi_id'])) {
        $ad_hoc_items['multipage_item'][$mpi_entry['mpi_id']] = $mpi_entry['mpi_id'];
      }
    }

    if (!empty($this->store->get('ad_hoc_items'))) {
      $this->source = new AdHocExporterSource($ad_hoc_items);
      $this->executable = new BatchExportExecutable($this->source, $this->exporter);
    }

    $this->executable->export();

    $form_state->setRedirect('mukurtu_export.export_results');
  }

  protected function saveListSelection(FormStateInterface $form_state): void {
    $listId = $form_state->getValue('export_list_id') ?: NULL;
    $this->setActiveExportListId($listId ? (int) $listId : NULL);
  }

  public function access(AccountInterface $account) {
    if (!$account->hasPermission('access mukurtu export')) {
      return AccessResult::forbidden();
    }
    // Default to CSV if no exporter has been selected yet.
    if (!$this->exporter) {
      $this->setExporterId('csv');
      $this->exporter = $this->exportPluginManager->getInstance(['id' => 'csv', 'configuration' => []]);
    }
    return AccessResult::allowed();
  }

  /**
   * Builds a display label for a community record using its community names.
   */
  protected function getCommunityRecordLabel(\Drupal\node\NodeInterface $node): string {
    if (!$node->hasField('field_communities')) {
      return $node->label();
    }
    $names = [];
    foreach ($node->get('field_communities')->referencedEntities() as $community) {
      $names[] = $community->getName();
    }
    return $names ? implode(', ', $names) : $node->label();
  }

}

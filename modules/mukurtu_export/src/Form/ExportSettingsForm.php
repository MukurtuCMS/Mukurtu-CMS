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
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($adHocItems['node'] ?? []));
      foreach ($nodes as $node) {
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
              'Include all items in sub-collections in this selection (1 additional item).',
              'Include all items in sub-collections in this selection (@count additional items).',
            ),
            '#default_value' => FALSE,
            '#weight' => -4,
            '#states' => [
              'visible'  => [':input[name="include_children"]' => ['checked' => TRUE]],
              'disabled' => [':input[name="include_children"]' => ['checked' => FALSE]],
            ],
          ];
        }
      }
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

    // Expand the selection to include children of collections/word lists.
    if ($form_state->getValue('include_children_recursive')) {
      $ad_hoc_items = $this->store->get('ad_hoc_items') ?? [];
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
      $ad_hoc_items = $this->store->get('ad_hoc_items') ?? [];
      foreach ($ad_hoc_items['node'] ?? [] as $node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
        if (!$node) {
          continue;
        }
        foreach ($this->childResolver->getChildEntities($node) as $child_type => $child_ids) {
          $ad_hoc_items[$child_type] = ($ad_hoc_items[$child_type] ?? []) + $child_ids;
        }
      }
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

}

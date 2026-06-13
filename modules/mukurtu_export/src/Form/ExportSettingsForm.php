<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
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

      // Show "Include child items" button when the selection contains
      // collections or word lists that haven't been expanded yet.
      if (!$this->store->get('ad_hoc_children_expanded')) {
        $child_count = 0;
        foreach ($adHocItems['node'] ?? [] as $node_id) {
          $node = $this->entityTypeManager->getStorage('node')->load($node_id);
          if ($node && in_array($node->bundle(), ['collection', 'word_list'])) {
            $children = $this->childResolver->getChildEntities($node);
            $child_count += array_sum(array_map('count', $children));
          }
        }
        if ($child_count > 0) {
          $form['actions']['include_children'] = [
            '#type' => 'submit',
            '#value' => $this->formatPlural($child_count, 'Include 1 child item', 'Include @count child items'),
            '#submit' => ['::submitExpandSelection'],
            '#limit_validation_errors' => [],
          ];
        }
      }
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
    $this->store->delete('ad_hoc_children_expanded');
    $form_state->setRedirect('mukurtu_export.export_settings');
  }

  /**
   * Submit handler for "Include child items" - expands selection with children.
   */
  public function submitExpandSelection(array &$form, FormStateInterface $form_state) {
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
    $this->store->set('ad_hoc_items', $ad_hoc_items);
    $this->store->set('ad_hoc_children_expanded', TRUE);
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

    $this->executable->export();

    $form_state->setRedirect('mukurtu_export.export_results');
  }

  /**
   * Provide an access check that ensures there is a result to report.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
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

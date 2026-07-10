<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface;
use Drupal\views_bulk_operations\Traits\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List picker form for the "Remove from export list" VBO bulk action.
 *
 * VBO redirects here (via confirm_form_route_name on RemoveFromExportListAction)
 * before executing the action. This form reads selected entities from the VBO
 * tempstore, lets the user pick which export list to remove them from, then
 * performs the removal and clears the VBO selection.
 */
class ExportListRemoveItemsForm extends FormBase {

  use ViewsBulkOperationsFormTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly ViewsBulkOperationsActionProcessorInterface $actionProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('views_bulk_operations.processor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_export_remove_items_from_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $view_id = NULL, ?string $display_id = NULL): array {
    $form_data = $this->getFormData($view_id, $display_id);

    if (!\array_key_exists('action_id', $form_data)) {
      $this->messenger()->addWarning($this->t('No items selected.'));
      $form_state->setRedirect('entity.export_list.collection');
      return $form;
    }

    $form_state->set('views_bulk_operations', $form_data);

    $form['list'] = $this->getListRenderable($form_data);

    // Show only export lists that contain at least one of the selected entities.
    $ids_by_type = [];
    foreach ($form_data['list'] as $item) {
      $ids_by_type[$item[2]][] = $item[3];
    }

    $options = $this->getExportListOptions($ids_by_type);

    if (empty($options)) {
      $this->messenger()->addWarning($this->t('The selected items are not in any export list.'));
      $this->deleteTempstoreData($form_data['view_id'], $form_data['display_id']);
      $form_state->setRedirectUrl($form_data['redirect_url']);
      return $form;
    }

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Remove from export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove from List'),
      '#button_type' => 'primary',
    ];
    $this->addCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_data = $form_state->get('views_bulk_operations');

    $list = $this->entityTypeManager->getStorage('export_list')
      ->load($form_state->getValue('export_list_id'));

    if (!$list) {
      $this->messenger()->addError($this->t('Could not find the export list.'));
      return;
    }

    // Parse selected entity IDs from VBO bulk form keys.
    // Each list item is [base_field_value, langcode, entity_type_id, entity_id].
    $by_type = [];
    foreach ($form_data['list'] as $item) {
      $entity_type = $item[2];
      $entity_id = $item[3];
      $by_type[$entity_type][$entity_id] = $entity_id;
    }

    // Remove entities from the export list (read-modify-write).
    $items = $list->getItems();
    $removed = 0;
    foreach ($by_type as $entity_type => $ids) {
      if (!isset($items[$entity_type])) {
        continue;
      }
      foreach ($ids as $id) {
        if (isset($items[$entity_type][$id])) {
          unset($items[$entity_type][$id]);
          $removed++;
        }
      }
      if (empty($items[$entity_type])) {
        unset($items[$entity_type]);
      }
    }
    $list->setItems($items)->save();

    $this->messenger()->addStatus($this->t('@count item(s) removed from export list %label.', [
      '@count' => $removed,
      '%label' => $list->label(),
    ]));

    $this->deleteTempstoreData($form_data['view_id'], $form_data['display_id']);
    $form_state->setRedirectUrl($form_data['redirect_url']);
  }

  /**
   * Returns export list options that contain at least one of the selected entities.
   *
   * @param array $ids_by_type
   *   Entity IDs grouped by entity type ID.
   *
   * @return array
   *   Keyed by export list ID, values are labels.
   */
  protected function getExportListOptions(array $ids_by_type): array {
    if (empty($ids_by_type)) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('export_list');
    $all_ids = $storage->getQuery()->accessCheck(TRUE)->sort('label')->execute();
    $lists = $storage->loadMultiple($all_ids);

    $options = [];
    foreach ($lists as $list) {
      $items = $list->getItems();
      foreach ($ids_by_type as $entity_type => $ids) {
        $list_items = $items[$entity_type] ?? [];
        foreach ($ids as $id) {
          if (isset($list_items[$id])) {
            $options[$list->id()] = $list->label();
            break 2;
          }
        }
      }
    }

    return $options;
  }

}

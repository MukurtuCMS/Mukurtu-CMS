<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Adds selected content to a named export list.
 *
 * Core's BulkForm calls execute() once per entity. Each call accumulates the
 * entity ID in a private tempstore and registers a batch (only on the first
 * call). The batch's finished() callback redirects to ExportListAddItemsForm,
 * which reads from the same tempstore and does the actual ExportList write.
 *
 * @Action(
 *   id = "mukurtu_export_add_to_list_action",
 *   label = @Translation("Add to export list"),
 *   type = "node",
 * )
 */
class AddToExportListAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   *
   * Called once per entity by core's BulkForm. Accumulates entity IDs in a
   * private tempstore; the form-alter submit handler redirects to the picker.
   */
  public function execute($object = NULL) {
    $store = \Drupal::service('tempstore.private')->get('mukurtu_export.add_items');
    $items = $store->get('entities') ?? [];
    $type = $object->getEntityTypeId();
    $items[$type] = $items[$type] ?? [];
    $items[$type][$object->id()] = $object->id();
    $store->set('entities', $items);
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    foreach ($entities as $entity) {
      $this->execute($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $account && $account->hasPermission('access mukurtu export')
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

}

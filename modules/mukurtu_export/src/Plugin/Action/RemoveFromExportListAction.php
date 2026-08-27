<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Removes selected content from a named export list.
 *
 * The actual work is performed by ExportListRemoveItemsForm, which VBO
 * redirects to via confirm_form_route_name. This execute() is a no-op.
 */
#[Action(
  id: 'mukurtu_export_remove_from_list_action',
  label: new TranslatableMarkup('Remove from export list'),
  confirm_form_route_name: 'mukurtu_export.remove_items_from_list',
  type: 'node',
)]
class RemoveFromExportListAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // No-op: ExportListRemoveItemsForm performs the actual work.
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

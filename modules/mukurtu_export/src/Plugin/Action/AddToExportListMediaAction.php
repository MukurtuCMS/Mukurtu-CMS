<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds selected media to a named export list.
 *
 * The actual work (entity-to-list assignment) is performed by
 * ExportListAddItemsForm, which VBO redirects to via confirm_form_route_name.
 * This execute() is a no-op.
 */
#[Action(
  id: 'mukurtu_export_add_to_list_media_action',
  label: new TranslatableMarkup('Add to export list'),
  confirm_form_route_name: 'mukurtu_export.add_items_to_list',
  type: 'media',
)]
class AddToExportListMediaAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // No-op: ExportListAddItemsForm performs the actual work.
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

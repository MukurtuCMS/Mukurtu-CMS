<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Exports selected media directly without adding it to a named list.
 *
 * VBO redirects to AdHocExportStartController::startBulk() which reads the
 * VBO tempstore, stores ad_hoc_items, and redirects to export settings.
 *
 * @Action(
 *   id = "mukurtu_export_adhoc_export_media_action",
 *   label = @Translation("Export"),
 *   type = "media",
 *   confirm_form_route_name = "mukurtu_export.start_adhoc_bulk",
 * )
 */
class AdHocExportMediaAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // No-op: the controller at start_adhoc_bulk performs the actual work.
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

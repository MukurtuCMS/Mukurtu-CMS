<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Starts an ad-hoc export for a single node without requiring a named list.
 */
class AdHocExportStartController extends ControllerBase {

  public function startNode(NodeInterface $node) {
    if (!\Drupal::currentUser()->hasPermission('access mukurtu export')) {
      throw new AccessDeniedHttpException();
    }

    $store = \Drupal::service('tempstore.private')->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', ['node' => [(int) $node->id() => (int) $node->id()]]);
    $store->set('exporter_id', 'csv');

    return $this->redirect('mukurtu_export.export_settings');
  }

}

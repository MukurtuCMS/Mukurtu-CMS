<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_export\Entity\ExportList;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Sets the active export list and starts the export workflow.
 */
class ExportListStartController extends ControllerBase {

  public function start(ExportList $export_list) {
    if (!$export_list->access('view')) {
      throw new AccessDeniedHttpException();
    }
    \Drupal::service('tempstore.private')
      ->get('mukurtu_import')
      ->set('export_list_id', (int) $export_list->id());

    return $this->redirect('mukurtu_export.export_item_and_format_selection');
  }

}

<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CsvExporterController extends ControllerBase {

  public function duplicate(CsvExporter $csv_exporter) {
    if (!$csv_exporter->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $dupe = $csv_exporter->createDuplicate();
    $uuid = str_replace('-', '_', $dupe->uuid());
    $dupe->set('id', $uuid);
    $dupe->set('label', $this->t('Copy of @label', ['@label' => $csv_exporter->label()]));
    $dupe->set('site_wide', FALSE);
    $dupe->setOwnerId($this->currentUser()->id());
    $dupe->save();

    return $this->redirect('entity.csv_exporter.edit_form', ['csv_exporter' => $dupe->id()]);
  }

}

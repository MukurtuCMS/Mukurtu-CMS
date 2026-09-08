<?php

namespace Drupal\mukurtu_export;

use Drupal\mukurtu_export\Entity\ExportList;

/**
 * Exporter source backed by a saved ExportList entity.
 */
class ExportListSource implements MukurtuExporterSourceInterface {

  protected ExportList $exportList;

  public function __construct(ExportList $export_list) {
    $this->exportList = $export_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    $entities = [];
    foreach ($this->exportList->getItems() as $entity_type_id => $ids) {
      foreach ($ids as $id) {
        $key = ExportItemIdentity::encode($id, $this->exportList->getItemLanguage($entity_type_id, $id));
        $entities[$entity_type_id][$key] = $key;
      }
    }
    return $entities;
  }

}

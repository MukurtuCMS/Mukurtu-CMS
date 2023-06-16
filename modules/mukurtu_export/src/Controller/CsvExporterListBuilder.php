<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;


class CsvExporterListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'mukurtu_export';
  }

  public function buildHeader()
  {
    $header['label'] = $this->t('Name');
    $header['machine_name'] = $this->t('Machine Name');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity)
  {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $row['label'] = $entity->label();
    $row['machine_name'] = $entity->id();
    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

  public function render()
  {
    $build[] = parent::render();
    return $build;
  }

}

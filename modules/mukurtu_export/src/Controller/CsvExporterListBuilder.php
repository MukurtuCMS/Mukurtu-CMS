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
    if ($entity->access('view')) {
      $row['label'] = $entity->label();
      $row['machine_name'] = $entity->id();
      $row['description'] = $entity->getDescription();
      return $row + parent::buildRow($entity);
    }
  }

  public function render()
  {
    $build['settings_link_top'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to export'),
      '#url' => \Drupal\Core\Url::fromRoute('mukurtu_export.export_settings'),
    ];
    $build[] = parent::render();
    $build['settings_link_bottom'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to export'),
      '#url' => \Drupal\Core\Url::fromRoute('mukurtu_export.export_settings'),
    ];
    return $build;
  }

}

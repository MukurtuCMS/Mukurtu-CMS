<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of mukurtu_import_strategies.
 */
class MukurtuImportStrategyListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['entity_type_id'] = $this->t('Target Entity Type ID');
    $header['bundle'] = $this->t('Target Bundle');
    $header['uid'] = $this->t('UID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\mukurtu_import2\MukurtuImportStrategyInterface $entity */
    $row['label'] = $entity->label();
    $row['entity_type_id'] = $entity->getTargetEntityTypeId();
    $row['bundle'] = $entity->getTargetBundle();
    $row['uid'] = $entity->getOwnerId();
    return $row + parent::buildRow($entity);
  }

}

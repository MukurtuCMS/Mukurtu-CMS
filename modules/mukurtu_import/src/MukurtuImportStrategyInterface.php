<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a mukurtu_import_strategy entity type.
 */
interface MukurtuImportStrategyInterface extends ConfigEntityInterface {
  public function getMapping();
  public function setMapping($mapping);
  public function setTargetEntityTypeId($entity_type_id);
  public function getTargetEntityTypeId();
  public function setTargetBundle($bundle);
  public function getTargetBundle();
}

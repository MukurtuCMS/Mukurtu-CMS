<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\file\FileInterface;

/**
 * Provides an interface defining a mukurtu_import_strategy entity type.
 */
interface MukurtuImportStrategyInterface extends ConfigEntityInterface, EntityOwnerInterface {
  public function getMapping();
  public function setMapping($mapping);
  public function setTargetEntityTypeId($entity_type_id);
  public function getTargetEntityTypeId();
  public function setTargetBundle($bundle);
  public function getTargetBundle();
  public function setLabel($label);
  public function getLabel();
  public function setConfig($key, $value);
  public function getConfig($key);
  public function applies(FileInterface $file);
  public function toDefinition(FileInterface $file);
  public function mappedFieldsCount(FileInterface $file);
}

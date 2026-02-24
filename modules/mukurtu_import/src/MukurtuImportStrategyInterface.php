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
  public function toDefinition(FileInterface $file, array $lookup_source_ids = []);
  public function mappedFieldsCount(FileInterface $file);

  /**
   * Get the source column mapped to the entity's label field.
   *
   * @return string|null
   *   The CSV column name mapped to the label field, or NULL if not mapped.
   */
  public function getLabelSourceColumn(): ?string;

  /**
   * Get the source column mapped to the media source field (e.g., filename).
   *
   * For media entity types, returns the CSV column mapped to the media type's
   * source field (e.g., field_media_image for Image media). Returns NULL for
   * non-media entity types or if the source field is not mapped.
   *
   * @return string|null
   *   The CSV column name mapped to the media source field, or NULL.
   */
  public function getMediaSourceColumn(): ?string;

  /**
   * Get the mapped target field name for a given source column.
   *
   * @param string $source
   *   The source column name from the CSV file.
   *
   * @return string|null
   *   The target field name if a mapping exists, NULL otherwise.
   */
  public function getMappedTarget(string $source): ?string;
}

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

  /**
   * Check if the strategy applies to the given CSV file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The CSV file to check.
   *
   * @return bool
   *   TRUE if the strategy applies to the file, FALSE otherwise.
   */
  public function applies(FileInterface $file): bool;

  public function toDefinition(FileInterface $file, array $lookup_source_ids = []);
  public function mappedFieldsCount(FileInterface $file);

  /**
   * Get identifier candidates that don't match any column in the file.
   *
   * Checks the configured identifier column and any mapping targeting the
   * entity's ID or UUID key against the file's real CSV headers. Used to
   * warn when row-matching silently fell back to record numbers because a
   * saved template's identifier column doesn't exist in this particular
   * file (see toDefinition()).
   *
   * @param \Drupal\file\FileInterface $file
   *   The CSV file to check.
   *
   * @return string[]
   *   The configured/mapped source column names that aren't present in the
   *   file's headers. Empty if all candidates match or none are configured.
   */
  public function getUnmatchedIdentifierColumns(FileInterface $file): array;

  /**
   * Get the source column mapped to the entity's label field.
   *
   * @return string|null
   *   The CSV column name mapped to the label field, or NULL if not mapped.
   */
  /**
   * Get the configured identifier column name.
   *
   * When set, this column is used as the migration source ID, taking
   * precedence over entity ID, UUID, and label column detection. It enables
   * cross-migration lookups by arbitrary user-defined values (e.g. for
   * paragraph entities that have no natural label).
   *
   * @return string|null
   *   The CSV column name, or NULL if not configured.
   */
  public function getIdentifierColumn(): ?string;

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

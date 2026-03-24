<?php

namespace Drupal\mukurtu_import;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for mukurtu_import_field_process plugins.
 */
interface MukurtuImportFieldProcessInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Returns the migrate process for the given field type.
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []);

  /**
   * Check if the plugin is applicable for a given field config.
   *
   * @param FieldDefinitionInterface $field_config
   * @return boolean
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool;

  /**
   * Get the format description.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL);

  /**
   * Get the supported properties for this field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   An array keyed by property name, with values being an array containing:
   *   - label: The human-readable label for the property.
   *   - description: The import format description for the property.
   */
  public function getSupportedProperties(FieldDefinitionInterface $field_definition): array;

}

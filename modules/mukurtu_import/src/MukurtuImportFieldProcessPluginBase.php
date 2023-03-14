<?php

namespace Drupal\mukurtu_import;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Base class for mukurtu_import_field_process plugins.
 */
class MukurtuImportFieldProcessPluginBase extends PluginBase implements MukurtuImportFieldProcessInterface {

  /**
   * An array of field types the process supports.
   *
   * @var array
   */
  public $field_types = [];
  public $weight = 0;


  /**
   * {@inheritdoc}
   */
  public function label() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool {
    return TRUE;
  }

}

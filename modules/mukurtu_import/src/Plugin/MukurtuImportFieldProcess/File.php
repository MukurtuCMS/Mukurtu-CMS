<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "file",
 *   label = @Translation("File"),
 *   field_types = {
 *     "file",
 *   },
 *   weight = 0,
 *   description = @Translation("File.")
 * )
 */
class File extends MukurtuImportFieldProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;

    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'uuid_lookup',
      'entity_type' => 'file',
    ];
    $process[] = [
      'plugin' => 'mukurtu_fileitem',
      'upload_location' => $context['upload_location'] ?? '',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $description = $this->isMultiple($field_config) ? "The file IDs or filenames of the uploaded files, separated by your selected multi-value delimiter." : "The file ID or filename of the uploaded file.";
    return t($description);
  }

}

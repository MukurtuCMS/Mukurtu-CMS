<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image",
 *   },
 *   weight = 0,
 *   description = @Translation("Image.")
 * )
 */
class Image extends MukurtuImportFieldProcessPluginBase {
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
      'plugin' => 'mukurtu_imageitem',
      'upload_location' => $context['upload_location'] ?? '',
    ];

    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $description = $this->isMultiple($field_config) ? "The file IDs or filenames of the uploaded images, separated by your selected multi-value delimiter." : "The file ID or filename of the uploaded image.";
    return t($description);
  }

}

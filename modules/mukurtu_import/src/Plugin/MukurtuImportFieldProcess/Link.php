<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "link",
 *   label = @Translation("Link"),
 *   field_types = {
 *     "link",
 *   },
 *   weight = 0,
 *   description = @Translation("Link.")
 * )
 */
class Link extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? ';';

    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'markdown_link',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $single_description = "The link in Markdown format: [Title](https://url.com)";
    $description = $this->isMultiple($field_config) ? "$single_description, separated by your selected multi-value delimiter." : $single_description;
    return t($description);
  }

}

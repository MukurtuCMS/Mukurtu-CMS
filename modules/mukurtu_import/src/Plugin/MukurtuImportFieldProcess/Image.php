<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'image',
  label: new TranslatableMarkup('Image'),
  description: new TranslatableMarkup('Image.'),
  field_types: ['image'],
  weight: 0,
  properties: ['target_id', 'alt'],
)]
class Image extends MukurtuImportFieldProcessPluginBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $subfield = $context['subfield'] ?? NULL;

    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    if ($subfield === 'target_id') {
      $process[] = [
        'plugin' => 'mukurtu_imageitem',
        'upload_location' => $context['upload_location'] ?? '',
      ];
    }
    if ($subfield === 'alt') {
      $process[] = [
        'plugin' => 'get',
      ];
    }

    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    if ($field_property == 'alt') {
      return $this->t('The alt text for the image.');
    }
    return $this->isMultiple($field_config) ? $this->t("The file IDs or filenames of the uploaded images, separated by your selected multi-value delimiter.") : $this->t("The file ID or filename of the uploaded image.");
  }

}

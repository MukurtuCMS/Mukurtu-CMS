<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'timestamp',
  label: new TranslatableMarkup('Timestamp'),
  description: new TranslatableMarkup('Timestamp.'),
  field_types: ['created', 'changed'],
  weight: 0,
)]
class Timestamp extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    return [
      // format_date turns a blank cell into '', which fails the field's
      // numeric validation. Skipping leaves the field unset instead, so new
      // content falls back to the import time and existing content keeps its
      // value (see ProtocolAwareEntityContent::import() and
      // restoreBlankedFieldValues()).
      [
        'plugin' => 'mukurtu_skip_on_blank',
        'source' => $source,
      ],
      [
        'plugin' => 'format_date',
        'from_format' => 'Y-m-d H:i:s',
        'to_format' => 'U',
        'from_timezone' => 'UTC',
        'to_timezone' => 'UTC',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    return t('Date and time in the format YYYY-MM-DD HH:MM:SS (UTC), e.g. 2026-08-27 14:40:14.');
  }

}

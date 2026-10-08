<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
  description: new TranslatableMarkup('Boolean.'),
  field_types: ['boolean'],
  weight: 0,
)]
class Boolean extends DefaultProcess {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    if ($this->isMultiple($field_config)) {
      return parent::getProcess($field_config, $source, $context);
    }

    // A blank cell would otherwise reach the field as '', which fails
    // validation. Skipping leaves the field unset instead, so new content
    // gets the field's default and existing content keeps its value.
    return [
      [
        'plugin' => 'mukurtu_skip_on_blank',
        'source' => $source,
      ],
    ];
  }

}

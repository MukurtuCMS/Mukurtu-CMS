<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\mukurtu_import\FormattedTextProcessCallback;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;

/**
 * Defines the FormattedText import field process plugin.
 */
#[MukurtuImportFieldProcess(
  id: 'formatted_text',
  label: new TranslatableMarkup('Formatted Text'),
  description: new TranslatableMarkup('Formatted Text.'),
  field_types: [
    'text',
    'text_long',
    'text_with_summary',
  ],
  weight: 0,
)]
class FormattedText extends MukurtuImportFieldProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []): array {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'callback',
      'callable' => new FormattedTextProcessCallback($context),
    ];
    $process[0]['source'] = $source;
    return $process;
  }

}

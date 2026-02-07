<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
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
      'callable' => [static::class, 'formattedTextValueFormat'],
    ];
    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * Formats a text value for a formatted text field.
   *
   * @param string $value
   *   The text value to format.
   *
   * @return array
   *   An array with 'value' and 'format' keys.
   */
  public static function formattedTextValueFormat(string $value): array {
    // @todo We will probably want to expose 'basic_html' as an option for the
    //   user to set at some point.
    return ['value' => $value, 'format' => 'basic_html'];
  }
}

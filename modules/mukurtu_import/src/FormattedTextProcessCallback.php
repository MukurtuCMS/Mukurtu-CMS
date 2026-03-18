<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Callback to use with migration callback process plugin.
 *
 * Sets the value for a formatted text field, including the value and format.
 */
class FormattedTextProcessCallback {

  /**
   * Constructs a new FormattedTextProcessCallback object.
   *
   * @param array $context
   *   Context for the Mukurtu import strategy that defines the active
   *   migration for import.
   */
  public function __construct(protected array $context) {
  }

  /**
   * Callback to use with migration callback process plugin.
   *
   * @param string $value
   *   The import field value for a formatted text field target field.
   *
   * @return array
   *   An array containing the value and format for the formatted text field.
   */
  public function __invoke(string $value): array {
    return ['value' => $value, 'format' => $this->context['default_format'] ?? MukurtuImportStrategy::DEFAULT_FORMAT];
  }

}

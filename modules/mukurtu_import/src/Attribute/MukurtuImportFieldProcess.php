<?php

namespace Drupal\mukurtu_import\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines mukurtu_import_field_process attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MukurtuImportFieldProcess extends Plugin {

  /**
   * Constructs a MukurtuImportFieldProcess attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the plugin.
   * @param array $field_types
   *   The field types this plugin supports.
   * @param int $weight
   *   The weight of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $field_types = [],
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}

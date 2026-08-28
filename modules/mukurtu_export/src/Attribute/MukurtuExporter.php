<?php

declare(strict_types=1);

namespace Drupal\mukurtu_export\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a MukurtuExporter attribute object.
 *
 * Plugin Namespace: Plugin\MukurtuExporter
 *
 * @see \Drupal\mukurtu_export\MukurtuExporterPluginManager
 * @see \Drupal\mukurtu_export\MukurtuExporterInterface
 * @see \Drupal\mukurtu_export\Plugin\ExporterBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MukurtuExporter extends Plugin {

  /**
   * Constructs a MukurtuExporter attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the exporter.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of the exporter.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}

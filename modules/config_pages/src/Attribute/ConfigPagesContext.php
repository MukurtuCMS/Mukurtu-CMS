<?php

declare(strict_types=1);

namespace Drupal\config_pages\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a config page context plugin attribute.
 *
 * Plugin Namespace: Plugin\ConfigPagesContext.
 *
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ConfigPagesContext extends Plugin {

  /**
   * Constructs a ConfigPagesContext attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the context.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}

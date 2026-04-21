<?php

namespace Drupal\search_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the Search API parse mode attribute.
 *
 * @see \Drupal\search_api\ParseMode\ParseModePluginManager
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see \Drupal\search_api\ParseMode\ParseModePluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiParseMode extends Plugin {

  /**
   * Constructs a new class instance.
   *
   * @param string $id
   *   The parse mode plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the parse mode plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The parse mode description.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param bool $no_ui
   *   (optional) TRUE to hide the plugin in the UI.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly bool $no_ui = FALSE,
  ) {}

}

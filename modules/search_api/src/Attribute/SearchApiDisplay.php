<?php

namespace Drupal\search_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Search API display annotation object.
 *
 * @see \Drupal\search_api\Display\DisplayPluginManager
 * @see \Drupal\search_api\Display\DisplayInterface
 * @see \Drupal\search_api\Display\DisplayPluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiDisplay extends Plugin {

  /**
   * Constructs a new class instance.
   *
   * @param string $id
   *   The display plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the display plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The display description.
   * @param string $index
   *   (optional) The ID of the display's index.
   * @param string|null $path
   *   (optional) The path to the search display, if any.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param bool $no_ui
   *   (optional) TRUE to hide the plugin in the UI.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $index = '',
    public readonly ?string $path = NULL,
    public readonly ?string $deriver = NULL,
    public readonly bool $no_ui = FALSE,
  ) {}

}

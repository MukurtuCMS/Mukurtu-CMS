<?php

namespace Drupal\search_api\Attribute;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an annotation for display plugins representing views.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiViewsDisplay extends SearchApiDisplay {

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
   * @param string|null $views_display_type
   *   (optional) The Views display type.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $index = '',
    public readonly ?string $path = NULL,
    public readonly ?string $deriver = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $views_display_type = NULL,
  ) {}

}

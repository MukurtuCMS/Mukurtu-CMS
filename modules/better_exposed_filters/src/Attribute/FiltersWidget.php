<?php

namespace Drupal\better_exposed_filters\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a bef filter attribute object.
 *
 * Plugin Namespace: Plugin\better_exposed_filters\filtersWidget.
 *
 * @see \Drupal\better_exposed_filters\BetterExposedFiltersWidgetBase
 * @see \Drupal\better_exposed_filters\BetterExposedFiltersWidgetInterface
 * @see \Drupal\better_exposed_filters\BetterExposedFiltersWidgetManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FiltersWidget extends Plugin {

  /**
   * Constructs a FiltersWidget attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   The label of the widget.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
  ) {}

}

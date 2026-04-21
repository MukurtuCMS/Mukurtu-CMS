<?php

namespace Drupal\search_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the Search API data type attribute.
 *
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see \Drupal\search_api\DataType\DataTypePluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiDataType extends Plugin {

  /**
   * Constructs a new class instance.
   *
   * @param string $id
   *   The data type plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the data type plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The data type description.
   * @param bool $default
   *   (optional) Whether this is one of the default data types provided by the
   *   Search API. Internal use only.
   * @param string $fallback_type
   *   (optional) The ID of the fallback data type for this data type. Needs to
   *   be one of the default data types defined in the Search API itself.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param bool $no_ui
   *   (optional) TRUE to hide the plugin in the UI.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $default = FALSE,
    public readonly string $fallback_type = 'string',
    public readonly ?string $deriver = NULL,
    public readonly bool $no_ui = FALSE,
  ) {}

}

<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a DataFetcher attribute object.
 *
 * Plugin namespace: Plugin\migrate_plus\data_fetcher.
 *
 * @see \Drupal\migrate_plus\DataFetcherPluginBase
 * @see \Drupal\migrate_plus\DataFetcherPluginInterface
 * @see \Drupal\migrate_plus\DataFetcherPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DataFetcher extends AttributeBase {

  /**
   * Constructs a DataFetcher attribute object.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The title of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    string $id,
    public readonly TranslatableMarkup|string $title,
    public readonly ?string $deriver = NULL,
  ) {
    parent::__construct($id);
  }

}

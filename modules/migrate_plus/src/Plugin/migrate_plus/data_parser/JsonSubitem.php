<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\data_parser;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_plus\Attribute\DataParser;

/**
 * Get a subitem set inside JSON data for migration.
 */
#[DataParser(
  id: 'json_subitem',
  title: new TranslatableMarkup('JSON subitem')
)]
class JsonSubitem extends Json {

  /**
   * {@inheritdoc}
   */
  protected function getSourceData(string $url, string|int $item_selector = '') {
    $source_data = parent::getSourceData($url, $item_selector);

    // If this is not the data item selector, skip getting subitems.
    if ($item_selector != $this->itemSelector) {
      return $source_data;
    }

    $subitems = [];
    $subitem_selectors = explode('/', trim((string) $this->configuration['subitem_selector'], '/'));
    foreach ($source_data as $source_data_item) {
      $subitem_base = $source_data_item;
      foreach ($subitem_selectors as $selector) {
        if (is_array($subitem_base) && array_key_exists($selector, $subitem_base)) {
          $subitem_base = $subitem_base[$selector];
        }
      }
      if (!empty($subitem_base)) {
        if (!is_array($subitem_base)) {
          // Not traversable, ignore.
          continue;
        }
        foreach ($subitem_base as $subitem) {
          if (in_array($subitem, $subitems)) {
            // Already there, skip.
            continue;
          }
          $subitems[] = $subitem;
        }
      }
    }
    return $subitems;
  }

}

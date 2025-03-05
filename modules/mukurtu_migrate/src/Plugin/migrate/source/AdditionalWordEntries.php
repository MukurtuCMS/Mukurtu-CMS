<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

/**
 * Get all dictionary word entry paragraphs that are NOT the base entry.
 *
 * @MigrateSource(
 *   id = "additional_word_entries"
 * )
 */
class AdditionalWordEntries extends ParagraphsItem
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $query = parent::query();
    $query->innerJoin('field_data_field_word_entry', 'w', 'w.field_word_entry_value = p.item_id');
    $query->fields('w', ['field_word_entry_value', 'delta']);
    $query->condition('w.delta', 0, '>');
    $query->condition('w.bundle', 'dictionary_word');
    $query->condition('p.bundle', 'dictionary_word_bundle');
    return $query;
  }
}

<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;
use Drupal\migrate\Row;

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

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $parentParagraphId = $row->getSourceProperty('field_word_entry_value');
    $query = $this->select('field_data_field_sample_sentence', 's')
      ->fields("s", ['entity_id', 'delta'])
      ->condition('s.entity_id', $parentParagraphId);

    $results = $query->execute()->fetchAll();
    // s.entity_id means the id of the PARAGRAPH that this SAMPLE SENTENCE field
    // is attached to.
    $sampleSentences = [];
    foreach ($results as $result) {
      $compoundId = $result['entity_id'] . ":" . $result['delta'];
      $sampleSentences[] = [ 'id' => $compoundId ];
    }
    $row->setSourceProperty('field_sentence', $sampleSentences);
    parent::prepareRow($row);
  }
}

<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

#[MigrateSource(id: 'mukurtu_v3_sample_sentence')]
class SampleSentence extends ParagraphsItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->innerJoin('field_data_field_sample_sentence', 'fds', 'fds.entity_id = p.item_id');
    // Join to the node field, field_word_entry, to be able to scope the rows
    // to only the first word entry, or only additional word entries.
    $query->innerJoin('field_data_field_word_entry', 'fdw', 'fdw.field_word_entry_value = p.item_id');
    // We will use the delta on field_sample_sentence to give us a unique
    // combined identifier that includes the paragraph id and delta.
    $query->fields('fds', ['delta']);
    // We effectively allow for the source plugin to be used in two different
    // modes to facilitate two different migrations. One scenario
    // (is_first == TRUE) is meant to limit the scope of sample sentences to
    // only ones that are set on the first word entry on a Dictionary Word. The
    // other scenario (is_first == FALSE) is meant to limit the scope of sample
    // sentences to only ones that are set on the second or later word entries
    // on a Dictionary Word.
    if ($this->configuration['is_first']) {
      $query->condition('fdw.delta', 0);
    }
    else {
      $query->condition('fdw.delta', 0, '>');
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);
    // Since this is based on ParagraphsItem, it is going to include all the
    // field data from the paragraph. We only want the specific delta sample
    // sentence for our row, since we joined on the field_sample_sentence table
    // in order to migrate one sentence at a time. Pick out the relevant delta
    // for easy process mapping.
    $delta = $row->getSourceProperty('delta');
    $field_sample_sentence = $row->getSourceProperty('field_sample_sentence');
    $row->setSourceProperty('sample_sentence', [$field_sample_sentence[$delta]]);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = parent::getIds();
    $ids['delta'] = [
      'type' => 'integer',
      'alias' => 'fds',
    ];
    return $ids;
  }

}

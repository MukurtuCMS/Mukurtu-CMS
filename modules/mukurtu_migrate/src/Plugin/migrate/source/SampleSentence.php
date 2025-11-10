<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Row;
use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

/**
 * Migration source for Sample Sentences.
 *
 * Puts together a Sample Sentence source by staring with dictionary_word_bundle
 * Paragraphs and joining on the field_sample_sentence field to effectively
 * have a Paragraph source for each sample sentence on a dictionary_word_bundle.
 * This is b/c Sample Sentences in v4 are their own Pargraph, whereas in v3,
 * they existed as a multi-value text field on the dictionary_word_bundle
 * paragraph.
 */
#[MigrateSource(id: 'mukurtu_v3_sample_sentence')]
class SampleSentence extends ParagraphsItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Joining on the multi-value field field_sample_sentence, effectively
    // produces a source Paragraph for each sample sentence. Ths is what we
    // want given that we're migrating each sentence on this multi-value text
    // field into a Paragraph in v4.
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
    $sample_sentence = $field_sample_sentence[$delta];

    // Look for tokens like [scald=2:sdl_editor_representation]. These may
    // occur on their own, or in the midst of other text.
    $regex = "/\[scald=(\d+):s[qd]l_editor_representation]/";
    if (preg_match($regex, $sample_sentence['value'], $matches) > 0) {
      // Remove the token from the sample sentence for later save as
      // sample_sentence. When we find a token, pull the sid value and save
      // as a source property for later lookup.
      $sample_sentence['value'] = trim(preg_replace($regex, '', $sample_sentence['value']));
      // Just take the first match.
      if (isset($matches[1])) {
        $row->setSourceProperty('sid', $matches[1]);
      }
    }

    $row->setSourceProperty('sample_sentence', [$sample_sentence]);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['sample_sentence'] = $this->t('The singular sentence text meant to be mapped to the new Sample Sentence paragraph in Mukurtu v4.');
    $fields['sid'] = $this->t('The Scald Atom ID of the sample sentence, if any.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = parent::getIds();
    // Since we're joining on the field_sample_sentence multi-value field, the
    // paragraph id would not be enough on its own to uniquely identify a row.
    // We add the delta for the field_sample_sentence field.
    $ids['delta'] = [
      'type' => 'integer',
      'alias' => 'fds',
    ];
    return $ids;
  }

}

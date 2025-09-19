<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_sample_sentence"
 * )
 */
class SampleSentence extends SqlBase
{

  protected $sampleSentences;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator()
  {
    $this->sampleSentences = [];

    // w.entity_id means the id of the NODE that the paragraph containing the SAMPLE SENTENCE field is attached to.
    // s.entity_id means the id of the PARAGRAPH that this SAMPLE SENTENCE field is attached to.
    $query = $this->select('field_data_field_word_entry', 'w')
      ->fields("w", ['entity_id', 'bundle', 'entity_id', 'revision_id', 'delta', 'field_word_entry_value', 'field_word_entry_revision_id'])
      ->fields("s", ['entity_id', 'revision_id', 'delta', 'field_sample_sentence_value'])
      ->condition('w.bundle', 'dictionary_word');

    if (isset($this->configuration['is_base'])) {
      if ($this->configuration['is_base']) {
        // add additional query condition to restrict results to those sample sentences attached to base word entries (delta 0)
        $query->condition('w.delta', 0);
      }
      else {
        $query->condition('w.delta', 0, '>');
      }
    }

    $query->leftJoin('field_data_field_sample_sentence', 's', 's.entity_id = w.field_word_entry_value');
    $result = $query->execute()->fetchAll();

    // Populate our sample sentence array from the query results.
    // w.entity_id means the id of the entity this word entry is attached to!! It will be a dictionary word NODE
    // s.entity_id means the id of the PARAGRAPH that this SAMPLE SENTENCE field is attached to.
    foreach ($result as $row) {
      $compoundId = $row['s_entity_id'] . ":" . $row['s_delta'];
      $this->sampleSentences[] = [
        'id' => $compoundId,
        'parent_node_id' => $row['w_entity_id'],
        'parent_paragraph_id' => $row['s_entity_id'],
        'field_sample_sentence_value' => $row['field_sample_sentence_value']
      ];
    }

    return new \ArrayIterator($this->sampleSentences);
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      'id' => $this->t("Sample sentence compound id in format 'entity_id:delta'"),
      'entity_id' => $this->t('Id of the entity that this sample sentence is attached to'),
      'revision_id' => $this->t('Revision id of the entity that this sample sentence is attached to'),
      'delta' => $this->t('Represents how many sample sentence values are in the field'),
      'field_sample_sentence_value' => $this->t('The value of the sample sentence field, plain text')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return ['id' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Empty on purpose.
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    // Now, we need to prepare fields on the source row.
    // For the text, we need to split out the part about the scald id from the plain
    // text of the field.
    $regex = "/(\[scald=[0-9]{1,}:s[qd]l_editor_representation\])/";

    $text = $row->getSourceProperty('field_sample_sentence_value');

    //capture all "[scald=" text in that $matches array
    preg_match_all($regex, $text, $matches);

    // Since all non "[scald=" text is going to get concatenated into one string
    // replace the "[scald=" bit with a delimiter of our choice *$repl, above
    // We could try an alternating capture above, but it is possible a bracket
    // could be in the text and that would cause matching havoc.
    $newValue = preg_replace($regex, '', $text);
    $row->setSourceProperty('field_sentence', $newValue);
    if (isset($matches)) {
      $sid = $this->fetchEmbeddedScaldId(reset(reset($matches)));
      $row->setSourceProperty('sid', $sid);
    }

    return parent::prepareRow($row);
  }

  protected function fetchEmbeddedScaldId($text) {
    preg_match('/\d+/', $text, $matches);
    return reset($matches);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString()
  {
    return 'mukurtu_v3_sample_sentence';
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE)
  {
    $count = $this->doCount();
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void
  {
    $this->getIterator()->rewind();
    $this->next();
  }

  protected function doCount()
  {
    $iterator = $this->getIterator();
    return $iterator instanceof \Countable ? $iterator->count() : iterator_count($this->initializeIterator());
  }
}

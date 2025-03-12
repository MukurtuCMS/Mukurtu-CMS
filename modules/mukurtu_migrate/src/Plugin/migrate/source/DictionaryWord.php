<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node;

/**
 * Provide Dictionary Words as a source for migrate.
 *
 * The dictionary word data structure has changed from v3 to v4.
 * In v3, dictionary words are made up of base metadata fields and at least one
 * word entry paragraph. These word entry paragraphs allow the same dictionary
 * word to have multiple instances (such as for alternate uses of the same word)
 * without needing to duplicate the entire dictionary word entity.
 *
 * However in v4, we factored out one set of word entry fields outside their
 * paragraph context and copied them to the base dictionary word fields. Word
 * entry paragraphs are still supported, just under a new name of 'Additional
 * Word Entries.'
 *
 * Thus, this source plugin sets the first word entry paragraph as the base word
 * entry, i.e., it takes all the fields from the first word entry paragraph and
 * migrates them into v4 dictionary word base fields. All subsequent dictionary
 * word entries are migrated to the Additional Entries field.
 *
 * @MigrateSource(
 *   id = "dictionary_word"
 * )
 */

class DictionaryWord extends Node
{
  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->condition('n.type', 'dictionary_word');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    // Each row will have one dictionary word in there to begin with.
    // Here's where we add the word entries.
    $fieldData = NULL;

    $nid = $row->getSourceProperty('nid');
    $q = $this->getDatabase()->select('field_data_field_word_entry', 'w');
    $q->addField('w', 'field_word_entry_value', 'id');

    // Okay, technically the revision info for the base word entry is not preserved here.
    // Should we do something about it? I don't really know. Hopefully when people do migrations,
    // they make a backup of their entire site, so if they needed any revision info for this paragraph,
    // hopefully they can get it that way.
    $q->addField('w', 'field_word_entry_revision_id', 'revision_id');
    $q->condition('w.entity_id', $nid);
    $q->condition('w.delta', 0);
    $result = $q->execute()->fetchAssoc();
    if ($result) {
      $baseWordEntryId = isset($result['id']) ? $result['id'] : NULL;
      $wordEntryRevisionId = isset($result['revision_id']) ? $result['revision_id'] : NULL;
      // Using the $baseWordEntryId, do a query on all the word entry field data
      // tables and add those to the source row.
      $wordEntryFields = array_keys($this->getFields('paragraphs_item', 'dictionary_word_bundle'));
      // Query the database for these fields' data.
      foreach ($wordEntryFields as $fieldName) {
        $tableName = 'field_data_' . $fieldName;
        $fieldQuery = $this->getDatabase()->select($tableName, 't');
        $fieldQuery->condition('t.entity_id', $baseWordEntryId);
        // We have to modify the query columns depending on the field so the query syntax is correct.
        switch ($fieldName) {
          case 'field_dictionary_word_recording':
            // MULTIVALUE FIELD!!
            $fieldQuery->addField('t', $fieldName . '_sid', 'target_id');
            // Not sure what the options portion was used for, but it's currently not in v4.
            //$fieldQuery->addField('t', $fieldName . '_options');
            break;
          case 'field_part_of_speech':
            $fieldQuery->addField('t', $fieldName . '_tid', 'target_id');
            break;
          # TODO case 'field_sample_sentence':
          # Sample sentence is a paragraph, so it is an entity reference field.
          case 'field_pronunciation':
            $fieldQuery->addField('t', $fieldName . '_value', 'value');
            $fieldQuery->addField('t', $fieldName . '_format', 'format');
            break;
          default:
            $fieldQuery->addField('t', $fieldName . '_value', 'value');
            break;
          }

        $fieldData = $fieldQuery->execute();

        // Set the source row with the data.
        if ($fieldData) {
          switch ($fieldName) {
              // Multivalue fields
            case 'field_dictionary_word_recording':
            case 'field_sample_sentence':
              $sourceData = [];
              while ($resultRow = $fieldData->fetchAssoc()) {
                $sourceData[] = $resultRow;
              }
              break;
              // Single value fields
            case 'field_part_of_speech':
              $sourceData = NULL;
              $fetched = $fieldData->fetchAssoc() ?? NULL;
              if ($fetched && isset($fetched['target_id'])) {
                $sourceData = $fetched['target_id'];
              }
              break;
            case 'field_pronunciation':
              $sourceData = NULL;
              $fetched = $fieldData->fetchAssoc() ?? NULL;
              $sourceData = isset($fetched) ? $fetched : NULL;
              break;
            default:
              $sourceData = NULL;
              $fetched = $fieldData->fetchAssoc() ?? NULL;
              if ($fetched && isset($fetched['value'])) {
                $sourceData = $fetched['value'];
              }
              break;
          }
          $row->setSourceProperty($fieldName, $sourceData);
        }
      }
    }
    // Now get the additional word entries.
    $additionalQuery = $this->getDatabase()->select('field_data_field_word_entry', 'w');
    $additionalQuery->addField('w', 'field_word_entry_value', 'value');
    $additionalQuery->addField('w', 'field_word_entry_revision_id', 'revision_id');
    $additionalQuery->condition('w.entity_id', $nid);
    // Get all the word entries that aren't the 1st one.
    $additionalQuery->condition('w.delta', 0, '>');
    $result = $additionalQuery->execute();
    while ($resultRow = $result->fetchAssoc()) {
      $additionalWordEntries[] = $resultRow;
    }
    $row->setSourceProperty('field_additional_word_entries', $additionalWordEntries);
    return parent::prepareRow($row);
  }
}

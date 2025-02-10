<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provide Dictionary Words as a source for migrate.
 *
 * Dictionary word migration needs custom handling. For each dictionary word:
 *  - Set the first word entry paragraph as the base dictionary word entry:
 *    - i.e., take all the fields from the first word entry paragraph and shove em into v4 dictionary word fields
 *  - For all subsequent dictionary word entries, migrate them to the Additional Entries field
 *
 * Why can't we use a process plugin for this, you ask? Because we need to touch the database to restructure the source data from it.
 * It looks like process plugins are more for transforming objects on a 1:1 basis,
 * meant as more of a downstream operation. I.e., it's expected you'll have gathered your data from the database and now you just want to tweak it.
 * Listen, there are hacky ways to get a database connection in a process plugin--just look at the process plugins for the TK labels.
 * But that's probably not best practice and I'd like to avoid that if possible, even if some redundant code is created as a result.
 *
 * @MigrateSource(
 *   id = "dictionary_word_migration"
 * )
 */

class DictionaryWord extends FieldableEntity
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Tables we need to query and which columns on it are relevant:

    // Node table (where node_type == 'dictionary_word')

    // Word Entry Paragraph db tables:
    // - field_data_field_word_entry: entity_type, entity_id, paragraph bundle name (machine name: bundle), field_word_entry_value (paragraph item id), revisions (revisit later)
    // - paragraphs_item: used as a go-between table more or less between field_data_field_word_entry and all your word entry field tables (i.e. field_data_field_alternate_spelling, field_data_field_<word entry field title here>)
    // - field_data_field_alternate_spelling: the actual meat of the fields to be migrated

    $fields = array_keys($this->fields());
    $query = $this->select('node', 'n')->fields('n', $fields);
    $query->condition('n.type', 'dictionary_word')->distinct();

    // Here's what's the hecks goin on:
    // We are querying the node table for dictionary words and doing an inner join on field_data_field_word_entry table.
    // Non-tech translation: get all dictionary word entities AND each one's word entries.

    // fields from node table that we need:
    //  - nid
    //  - vid
    //  - type (dictionary_word in this case)
    //  - language
    //  - title
    //  - uid
    //  - status
    //  - created
    //  - changed

    // fields from field_data_field_word_entry table that we need:
    //  - entity_type (needed for validation, node in this case)
    //  - bundle (also needed for validation, dictionary_word in this case)
    //  - entity_id
    //  - revision_id
    //  - language
    //  - delta (used to differentiate multiple word entries. The first one will be at delta == 0. Then for any rows that have delta > 0, that represents a word entry to friggin shove into the paragraphs array)
    //  - field_word_entry_value (paragraph item id)
    //  - field_word_entry_revision_value (paragraph item revision id)

    // ASIDE: For context, this paragraph item id links to the paragraphs_item table. The columns here are:
      // - item_id
      // - revision_id
      // - paragraph item bundle (dictionary_word_bundle here)
      // - field name of host entity (field_word_entry here),
      // - archived status
    //  We do not need to query this table (I think?) since we already have the info inside it.
    //  Remember, we are migrating this db data to a dictionary word's field_additional_entries field,
    // which is an *entity reference* field, meaning it only needs to store the node id of the referenced entity.
    // That makes it easier on us to migrate this data.

    // END ASIDE: Anyway, we query the node table for all nodes that are dictionary words.
    // Then we do an inner join on the field_data_field_word_entry table, join on nid == entity_id.

    // THEN, filter the results even more and only pick the 1st word entry referenced in the field.
    // Naturally this is at delta = 0.
    // We will use this word entry as the base word entry.
    // Question: do we need to enforce distinct() here? Are we guaranteed to get unique results? Just gotta test I guess.
    $query->condition('w.delta', 0);
    $query->innerJoin('field_data_field_word_entry', 'w', 'w.entity_id = n.nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    // Each row will have one dictionary word in there to begin with.
    // Here's where we add the word entries.
    // TODO: we may not need to gather dictionary word fields like this, since we
    // already queried for those inside of query(), but there might be some other admin field info
    // that we might have overlooked. I just want to be sure.
    // If we don't need it, great!
    $fields = array_keys($this->getFields('node', 'dictionary_word'));
    /**
     * // foreach ($fields as $field) {
     * //   $nid = $row->getSourceProperty('nid');
     * //   $row->setSourceProperty($field, $this->getFieldValues('scald_atom', $field, $sid));
     * // }

     * // But then we will need to run another query to get the paragraphs that are at deltas > 0.

     * // We can probably use that method $this->getDatabase()->select() inside of prepareRow().
     * // My thinking is, for each dictionary word, query for the rest of the word entry paragraphs that have delta <> 0.
     * // Then we package these into an array that v4 expects:
     * // field_additional_word_entries => [
     * //     0 => [
     * //       "target_id" => 4,
     * //       "target_revision_id" => 4
     * //     ],
     * //     1 => [
     * //       "target_id" => 5,
     * //       "target_revision_id" => 5
     * //     ]
     * //   ]
     */
    return parent::prepareRow($row);
  }

  protected function v3WordEntryBaseFields() {

  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    $fields = [
      'nid' => $this->t('NID'),
      'vid' => $this->t('VID'),
      'language' => $this->t('Language'),
      'title' => $this->t('Title'),
      'uid' => $this->t('UID'),
      'status' => $this->t('Status'),
      'created' => $this->t('Created'),
      'changed' => $this->t('Changed'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    $ids['nid']['type'] = 'integer';
    $ids['nid']['alias'] = 'n';
    return $ids;
  }
}

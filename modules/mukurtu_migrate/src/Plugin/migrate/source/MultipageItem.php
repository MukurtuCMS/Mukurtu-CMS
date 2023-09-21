<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provide MPIs as a source for migrate.
 *
 * @MigrateSource(
 *   id = "d7_multipage_item",
 *   source_module = "ma_digitalheritage",
 *   source_provider = "ma_digitalheritage"
 * )
 */

class MultipageItem extends FieldableEntity {
  /**
   * {@inheritdoc}
   */
  public function query() {
    $fields = array_keys($this->fields());
    $query = $this->select('node', 'n')->fields('n', $fields);
    $query->innerJoin('field_data_field_book_parent', 'p', 'p.field_book_parent_target_id = n.nid');
    $query->condition('n.type', 'digital_heritage', '=')->distinct();
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');
    $vid = $row->getSourceProperty('vid');
    $children = $this->getFieldValues('node', 'field_book_children', $nid, $vid) ?? [];
    $pages = array_merge([['target_id' => $nid]], $children);
    $row->setSourceProperty('field_pages', $pages);
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = array(
      'nid' => $this->t('Node ID'),
      'vid' => $this->t('Revision ID'),
      'title' => $this->t('Title'),
      'status' => $this->t('Status'),
      'created' => $this->t('Created'),
      'changed' => $this->t('Changed'),
    );
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid']['type'] = 'integer';
    $ids['nid']['alias'] = 'n';
    return $ids;
  }
}

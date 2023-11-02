<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provide Scald Atoms as a source for migrate.
 *
 * @MigrateSource(
 *   id = "d7_scald_atom",
 *   source_module = "scald",
 *   source_provider = "scald"
 * )
 */
class ScaldAtom extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
   public function query() {
    $fields = array_keys($this->fields());
    $query = $this->select('scald_atoms', 's')->fields('s', $fields);
    if (isset($this->configuration['atom_type'])) {
      $query->condition('s.type', $this->configuration['atom_type']);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $fields = array_keys($this->getFields('scald_atom', $row->getSourceProperty('type')));
    foreach ($fields as $field) {
      $sid = $row->getSourceProperty('sid');
      $row->setSourceProperty($field, $this->getFieldValues('scald_atom', $field, $sid));
    }
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
   public function fields() {
    $fields = [
      'sid' => $this->t('Scald Atom ID'),
      'provider' => $this->t('Provider module name'),
      'type' => $this->t('Scald Atom type'),
      'base_id' => $this->t('Scald Atom base ID'),
      'language' => $this->t('Scald Atom language'),
      'publisher' => $this->t('Scald Atom publisher (User ID)'),
      'actions' => $this->t('Available Scald actions'),
      'title' => $this->t('Scald Atom title'),
      'data' => $this->t('Scald Atom data'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['sid']['type'] = 'integer';
    $ids['sid']['alias'] = 's';
    return $ids;
  }

}

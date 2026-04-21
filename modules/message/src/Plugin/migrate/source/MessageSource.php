<?php

namespace Drupal\message\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 message source from database.
 *
 * Available configuration keys:
 * - bundle: (optional) The message types to filter messages retrieved from the
 *   source - can be a string or an array. If omitted, all messages are
 *   retrieved.
 *
 * @MigrateSource(
 *   id = "d7_message_source",
 *   source_module = "message"
 * )
 */
class MessageSource extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('message', 'm');

    $query->fields('m', [
      'mid',
      'type',
      'arguments',
      'uid',
      'timestamp',
      'language',
    ]);

    if (isset($this->configuration['bundle'])) {
      $query->condition('m.type', (array) $this->configuration['bundle'], 'IN');
    }

    $query->orderBy('timestamp');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'mid' => $this->t('Primary key: unique message ID.'),
      'type' => $this->t('Message type.'),
      'arguments' => $this->t('Message replace arguments.'),
      'uid' => $this->t('Message uid of author.'),
      'timestamp' => $this->t('Message create time.'),
      'language' => $this->t('Message language.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['mid']['type'] = 'integer';

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Get Field API field values.
    $mid = $row->getSourceProperty('mid');
    foreach (array_keys($this->getFields('message', $row->getSourceProperty('type'))) as $field) {
      $row->setSourceProperty($field, $this->getFieldValues('message', $field, $mid));
    }
    return parent::prepareRow($row);
  }

}

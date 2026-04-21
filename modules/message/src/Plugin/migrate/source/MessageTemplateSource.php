<?php

namespace Drupal\message\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 message types source from database.
 *
 * @MigrateSource(
 *   id = "d7_message_template_source",
 *   source_module = "message"
 * )
 */
class MessageTemplateSource extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('message_type', 'mt');

    $query->join('field_data_message_text', 'txt', 'mt.id = txt.entity_id');

    $query->fields('mt', [
      'id',
      'category',
      'description',
      'argument_keys',
      'language',
      'status',
      'arguments',
      'data',
    ]);

    $query->fields('txt', [
      'message_text_value',
      'message_text_format',
      'delta',
    ]);

    $expression = 'right(mt.name, ' . EntityTypeInterface::ID_MAX_LENGTH . ')';
    $query->addExpression($expression, 'name');
    $query->addExpression("concat(mt.id, txt.delta)", 'concat_id');
    $query->orderBy('mt.id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Primary key: unique message type ID.'),
      'name' => $this->t('Unique message type name.'),
      'category' => $this->t('Message type category.'),
      'description' => $this->t('Message type description.'),
      'argument_keys' => $this->t('Message type augmented keys.'),
      'language' => $this->t('Message type language.'),
      'status' => $this->t('Message type status.'),
      'module' => $this->t('Message type module.'),
      'arguments' => $this->t('Message type arguments.'),
      'data' => $this->t('Message type data.'),
      'message_text_value' => $this->t('Message text value.'),
      'message_text_format' => $this->t('Message text format.'),
      'delta' => $this->t('Message text number.'),
      'concat_id' => $this->t('Concat the id of the message type and the delta of the message text.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['concat_id']['type'] = 'integer';

    return $ids;
  }

}

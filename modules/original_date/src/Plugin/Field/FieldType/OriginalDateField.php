<?php

namespace Drupal\original_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'original_date' field type.
 *
 * @FieldType(
 * id = "original_date",
 * label = @Translation("Original Date"),
 * module = "original_date",
 * description = @Translation("Date the item was originally published."),
 * default_widget = "original_date_text",
 * default_formatter = "original_date_formatter"
 * )
 */
class OriginalDateField extends FieldItemBase
{
  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition)
  {
    return array(
      'columns' => array(
        'date' => array(
          'type' => 'text',
          'size' => 'tiny',
          'not null' => false,
        ),
        'timestamp' => array(
          'type' => 'int',
          'size' => 'big',
          'not null' => false,
        ),
        'year' => array(
          'type' => 'varchar',
          'length' => 4,
          'not null' => false,
        ),
        'month' => array(
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => true,
        ),
        'day' => array(
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => true,
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty()
  {
    $date = $this->get('date')->getValue();
    $timestamp = $this->get('timestamp')->getValue();
    $year = $this->get('year')->getValue();
    $month = $this->get('month')->getValue();
    $day = $this->get('day')->getValue();

    return empty($date) && empty($timestamp) && empty($year) && empty($month) && empty($day);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
  {
    $properties['date'] = DataDefinition::create('string')
      ->setLabel(t('Original date'));

    $properties['timestamp'] = DataDefinition::create('integer')
      ->setLabel(t('UNIX Timestamp'));

    $properties['year'] = DataDefinition::create('string')
      ->setLabel(t('Year'));

    $properties['month'] = DataDefinition::create('integer')
      ->setLabel(t('Month'));

    $properties['day'] = DataDefinition::create('integer')
      ->setLabel(t('Day'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = true)
  {
    $year = $values['year'];
    $month = $values['month'];
    $day = $values['day'];

    // 1. Store the date string for the user-facing date display
    $date = $year; // year is guaranteed

    $date = $date . ($month ? ("-" . str_pad(strval($month), 2, "0", STR_PAD_LEFT)) : "");

    $date = $date . ($day ? ("-" . str_pad(strval($day), 2, "0", STR_PAD_LEFT)) : "");

    $values['date'] = $date;

    // 2. Calculate the internal date

    $timestamp = strtotime($date);

    $values['timestamp'] = $timestamp;

    parent::setValue($values, $notify);
  }
}

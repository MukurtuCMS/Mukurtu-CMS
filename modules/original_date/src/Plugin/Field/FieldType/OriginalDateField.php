<?php

namespace Drupal\original_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use InvalidArgumentException;

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
class OriginalDateField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'date' => [
          'type' => 'text',
          'size' => 'tiny',
          'not null' => FALSE,
        ],
        'timestamp' => [
          'type' => 'int',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'year' => [
          'type' => 'varchar',
          'length' => 4,
          'not null' => TRUE,
        ],
        'month' => [
          'type' => 'varchar',
          'length' => 2,
          'not null' => FALSE,
        ],
        'day' => [
          'type' => 'varchar',
          'length' => 2,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
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
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['date'] = DataDefinition::create('string')
      ->setLabel(t('Original date'));

    $properties['timestamp'] = DataDefinition::create('integer')
      ->setLabel(t('UNIX Timestamp'));

    $properties['year'] = DataDefinition::create('string')
      ->setLabel(t('Year'));

    $properties['month'] = DataDefinition::create('string')
      ->setLabel(t('Month'));

    $properties['day'] = DataDefinition::create('string')
      ->setLabel(t('Day'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      // Handle unprocessed values coming directly from set, rather than the
      // widget.
      $dateComponents = explode('-', $values);
      $values = [];

      $values['year'] = $dateComponents[0] ?? '';
      $values['month'] = $dateComponents[1] ?? '';
      $values['day'] = $dateComponents[2] ?? '';

      if (!$values['year'] || !is_numeric($values['year']) ||
        intval($values['year']) < 1) {
        throw new InvalidArgumentException("Invalid year '{$values['year']}'.");
      }
      if ($values['month'] && (!is_numeric($values['month']) ||
        intval($values['month']) < 1 || intval($values['month']) > 12)) {
        throw new InvalidArgumentException("Invalid month '{$values['month']}'.");
      }
      if ($values['day'] && (!is_numeric($values['day']) ||
        intval($values['day']) < 1 || intval($values['day']) > 31)) {
        throw new InvalidArgumentException("Invalid day '{$values['day']}'.");
      }

      // Store the date string for the user-facing date display.
      // Year is guaranteed.
      $date = $values['year'];
      $date = $date . ($values['month'] ? ("-" . str_pad($values['month'], 2, "0", STR_PAD_LEFT)) : "");
      $date = $date . ($values['day'] ? ("-" . str_pad($values['day'], 2, "0", STR_PAD_LEFT)) : "");

      $values['date'] = $date;

      // Calculate the internal date.
      $timestamp = strtotime($date);

      $values['timestamp'] = $timestamp === FALSE ? NULL : $timestamp;
    }
    else {
      $year = $values['year'] ?? '';
      $month = $values['month'] ?? '';
      $day = $values['day'] ?? '';

      // Store the date string for the user-facing date display.
      // Year is guaranteed.
      $date = $year;
      $date = $date . ($month ? ("-" . str_pad(strval($month), 2, "0", STR_PAD_LEFT)) : "");
      $date = $date . ($day ? ("-" . str_pad(strval($day), 2, "0", STR_PAD_LEFT)) : "");

      $values['date'] = $date;

      // Calculate the internal date.
      $timestamp = strtotime($date);
      $values['timestamp'] = $timestamp === FALSE ? NULL : $timestamp;
    }
    parent::setValue($values, $notify);
  }
}

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
 * default_formatter = "yyyy_mm_dd_original_date_formatter"
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
          'length' => 5,
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

    return $date == '' && $timestamp == '' && $year == '' && $month == '' && $day == '';
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

  protected function validateDate($date) {
    $year = $date['year'];
    $month = $date['month'];
    $day = $date['day'];

    // Validate each component of the date.
    if ($year != '' && (intval($year) < 1 || intval($year) > 32767)) {
      throw new InvalidArgumentException("Invalid year '{$year}'.");
    }
    if ($month != '' && (intval($month) < 1 || intval($month) > 12)) {
      throw new InvalidArgumentException("Invalid month '{$month}'.");
    }
    if ($day != '' && (intval($day) < 1 || intval($day) > 31)) {
      throw new InvalidArgumentException("Invalid day '{$day}'.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      // Handle unprocessed values coming directly from set instead of widget.

      // Handle empty date.
      if ($values == '') {
        $values = [];
        $values['year'] = '';
        $values['month'] = '';
        $values['day'] = '';
      }
      else {
        // Perform regex matching for non-empty date.
        // Matches dates with - or / delimiter (e.g. 12-2-22 or 12/2/22).
        $exp_ymd = '~^([0-9]{1,4})[-/]([0-9]{1,2})[-/]([0-9]{1,2})$~';
        $exp_ym = '~^([0-9]{1,4})[-/]([0-9]{1,2})$~';
        $exp_y = '~^([0-9]{1,4})$~';
        if (preg_match($exp_ymd, $values, $matches) || preg_match($exp_ym, $values, $matches) || preg_match($exp_y, $values, $matches)) {
          $values = [];
          $values['year'] = isset($matches[1]) ? $matches[1] : '';
          $values['month'] = isset($matches[2]) ? $matches[2] : '';
          $values['day'] = isset($matches[3]) ? $matches[3] : '';

          $this->validateDate($values);
        }
        else {
          throw new InvalidArgumentException("Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.");
        }
      }
      // Strip leading zeros on date components, if present.
      $values['year'] = ltrim($values['year'], "0");
      $values['month'] = ltrim($values['month'], "0");
      $values['day'] = ltrim($values['day'], "0");

      // Store the date string for the user-facing date display.
      $year = $values['year'] ?? '';
      $month = $values['month'] ?? '';
      $day = $values['day'] ?? '';

      $date = $this->generateUserFacingDate($year, $month, $day);

      $values['date'] = $date;

      // Calculate the internal date.
      $timestamp = strtotime($date);

      $values['timestamp'] = $timestamp === FALSE ? NULL : $timestamp;
    }
    else {
      // Handle values coming from the original date widget.

      // Strip leading zeros on year, if present.
      if (!empty($values['year'])) {
        $values['year'] = ltrim($values['year'], '0');
      }

      $year = $values['year'] ?? '';
      $month = $values['month'] ?? '';
      $day = $values['day'] ?? '';

      $date = $this->generateUserFacingDate($year, $month, $day);

      // Date validation was performed earlier in the widget's validate().
      $values['date'] = $date;

      // Calculate the internal date.
      $timestamp = strtotime($date);
      $values['timestamp'] = $timestamp === FALSE ? NULL : $timestamp;
    }
    parent::setValue($values, $notify);
  }

  // Builds the date string for the user-facing date display.
  protected function generateUserFacingDate($year, $month, $day) {
    $date = $year;
    $date = $date . ($month ? ("-" . str_pad(strval($month), 2, "0", STR_PAD_LEFT)) : "");
    $date = $date . ($day ? ("-" . str_pad(strval($day), 2, "0", STR_PAD_LEFT)) : "");
    return $date;
  }
}

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
                'value' => array(
                    'type' => 'text',
                    'size' => 'tiny',
                    'not null' => FALSE,
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $value = $this->get('value')->getValue();
        return $value === NULL || $value === '';
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties['value'] = DataDefinition::create('string')
            ->setLabel(t('Original date'));
        $properties['year'] = DataDefinition::create('integer')
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
    public function preSave() {
        // TODO: put this into a dateParse() method and factor it out

        // check what format the date is in with regex from the field widget
        // then parse the date and store it appropriately

        $value = $element['#value'];

        $yearMonthDayExpression = '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/';
        $yearMonthExpression = '/^\d{4}-(0[1-9]|1[0-2])$/';
        $yearExpression = '/^\d{4}$/';

        // case 1: YYYY
        if (preg_match($yearExpression, $value)) {
            $properties['year'] = intval($value);
            // set the month and day to just be 01 and 01
            $properties['month'] = 1;
            $properties['day'] = 1;
        }
        // case 2: YYYY-MM
        else if (preg_match($yearMonthExpression, $value)) {
            $tokens = [];
            $tokens = explode("-", $value);

            $properties['year'] = intval($tokens[0]);
            $properties['month'] = intval($tokens[1]);
            $properties['day'] = 1;
        }
        // case 3: YYYY-MM-DD
        else if (preg_match($yearMonthDayExpression, $value)) {
            $tokens = [];
            $tokens = explode("-", $value);

            $properties['year']->setValue(intval($tokens[0]));
            $properties['month'] = intval($tokens[1]);
            $properties['day'] = intval($tokens[2]);
        }
    }
}

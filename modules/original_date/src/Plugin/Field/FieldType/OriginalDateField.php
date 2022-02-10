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
                'date_external' => array(
                    'type' => 'text',
                    'size' => 'tiny',
                    'not null' => FALSE,
                ),
                'date_internal' => array(
                    'type' => 'int',
                    'size' => 'tiny',
                    'not null' => FALSE,
                ),
                // the rest of these components are for the date widget, but do we need them?
                // TODO: make these unsigned?
                'year' => array(
                    'type' => 'text',
                    'size' => 'tiny',
                    'not null' => FALSE,
                ),
                'month' => array(
                    'type' => 'int',
                    'size' => 'tiny',
                    'unsigned' => TRUE,
                ),
                'day' => array(
                    'type' => 'int',
                    'size' => 'tiny',
                    'unsigned' => TRUE,
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $external = $this->get('date_external')->getValue();
        $internal = $this->get('date_internal')->getValue();
        return empty($external) && empty($internal); // TODO: can I use this
        // TODO: do we need to enforce isEmpty() on the widget components too?
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties['date_external'] = DataDefinition::create('string')
            ->setLabel(t('Original date'));

        $properties['date_internal'] = DataDefinition::create('integer')
            ->setLabel(t('Internal date'));
        //->setComputed(TRUE);

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
    public function preSave() {
        // TODO
    }
}

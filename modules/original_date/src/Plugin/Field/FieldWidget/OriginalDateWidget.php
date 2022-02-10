<?php

//https://www.drupal.org/docs/creating-custom-modules/creating-custom-field-types-widgets-and-formatters/create-a-custom-field-formatter

namespace Drupal\original_date\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'original_date_text' widget.
 *
 * @FieldWidget(
 *   id = "original_date_text",
 *   module = "original_date",
 *   label = @Translation("Original date the item was created."),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateWidget extends WidgetBase
{
    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        // TODO

        $element['year'] = array (
            '#type' => 'textfield',
            '#title' => t('Year'),
            '#size' => 5,
            '#maxlength' => 5,
            '#default_value' => isset($items[$delta]->year) ? $items[$delta]->year : '',
            '#element_validate' => [
                [static::class, 'validate'],
            ]
        );

        $element['month'] = array(
            '#type' => 'number',
            '#title' => t('Month'),
            '#size' => 5,
            '#default_value' => isset($items[$delta]->month) ? $items[$delta]->month : '',
            '#element_validate' => [
                [static::class, 'validate'],
            ]
        );

        $element['day'] = array(
            '#type' => 'number',
            '#title' => t('Day'),
            '#size' => 5,
            '#default_value' => isset($items[$delta]->day) ? $items[$delta]->day : '',
            '#element_validate' => [
                [static::class, 'validate'],
            ]
        );

        $element += array(
            '#type' => 'fieldset',
            '#attributes' => array('class' => array('container-inline')),
        );

        return ['value' => $element];
    }

    /**
     * Validate the original date text field.
     */
    public static function validate($element, FormStateInterface $form_state)
    {
        $year = $element['year']['#value'];
        $month = $form_state->getValue('month');
        $day = $form_state->getValue('day');

        //dpm($year, $month, $day);

        //dpm($form_state->getValues());

        $field_name = $element['#array_parents'][0];

        //dpm($field_name);

        dpm($form_state->getValue('field_original_date'));
    }
}

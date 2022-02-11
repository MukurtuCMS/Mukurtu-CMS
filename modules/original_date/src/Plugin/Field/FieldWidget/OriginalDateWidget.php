<?php

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
        $element += [
            '#element_validate' => [
                [$this, 'validate'],
            ]
        ];

        $element['date_external'] = array(
            '#type' => 'textfield',
            '#title' => t('Original Date'),
            '#size' => 10,
            '#maxlength' => 10,
            '#default_value' => isset($items[$delta]->date_external) ? $items[$delta]->date_external : '',
        );

        $element['year'] = array (
            '#type' => 'textfield',
            '#title' => t('Year'),
            '#size' => 4,
            '#maxlength' => 4,
            '#default_value' => isset($items[$delta]->year) ? $items[$delta]->year : '',
        );

        // TODO: how to prevent month and day dropdowns from going negative
        $element['month'] = array(
            '#type' => 'number',
            '#title' => t('Month'),
            '#size' => 5,
            '#default_value' => isset($items[$delta]->month) ? $items[$delta]->month : '',
        );

        $element['day'] = array(
            '#type' => 'number',
            '#title' => t('Day'),
            '#size' => 5,
            '#default_value' => isset($items[$delta]->day) ? $items[$delta]->day : '',
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
        $month = $element['month']['#value'];
        $day = $element['day']['#value'];

        // 1. ensure date in acceptable format
        if ($year && $month && $day ||
            $year && $month && !$day ||
            $year && !$month && !$day
        )
        {
            // 2. Ensure date is valid
            if (!$month) {
                $month = 1;
            }
            if (!$day) {
                $day = 1;
            }
            if (!checkdate($month, $day, $year)) {
                $form_state->setError($element, "Invalid date.");
            }
        }
        else {
            $form_state->setError($element, "Acceptable date formats are YYYY, YYYY-MM, or YYYY-MM-DD.");
        }
    }
}

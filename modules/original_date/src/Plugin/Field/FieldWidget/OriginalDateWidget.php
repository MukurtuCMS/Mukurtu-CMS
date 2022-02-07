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
        $value = isset($items[$delta]->value) ? $items[$delta]->value : '';
        $element += [
            '#type' => 'textfield',
            '#default_value' => $value,
            '#size' => 10,
            '#maxlength' => 10,
            '#element_validate' => [
                [static::class, 'validate'],
            ],
        ];
        return ['value' => $element];
    }

    /**
     * Validate the original date text field.
     */
    public static function validate($element, FormStateInterface $form_state)
    {
        $value = $element['#value'];
        $valueToLower = strtolower($value);

        $yearMonthDayExpression = '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/';
        $yearMonthExpression = '/^\d{4}-(0[1-9]|1[0-2])$/';
        $yearExpression = '/^\d{4}$/';

        if (strlen($value) == 0) {
            $form_state->setValueForElement($element, '');
            return;
        }

        // check if the date matches one of the three valid formats
        if (
            !preg_match($yearMonthDayExpression, $valueToLower) &&
            !preg_match($yearMonthExpression, $valueToLower) &&
            !preg_match($yearExpression, $valueToLower)
        ) {
            $form_state->setError($element, t("Date must be in format YYYY, YYYY-MM, or YYYY-MM-DD."));
        }
    }
}

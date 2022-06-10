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
 *   label = @Translation("Original date text fields"),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      $year = $value['value']['year'] ?? '';
      $month = $value['value']['month'] ?? '';
      $day = $value['value']['day'] ?? '';

      $monthCleaned = (int) ltrim($month, "0");
      $dayCleaned = (int) ltrim($day, "0");

      $values[$key]['year'] = ltrim($year, "0");
      $values[$key]['month'] = $monthCleaned ? $monthCleaned : NULL;
      $values[$key]['day'] = $dayCleaned ? $dayCleaned : NULL;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#element_validate' => [
        [$this, 'validate'],
      ]
    ];

    $element['year'] = [
      '#type' => 'textfield',
      '#title' => t('Year'),
      '#size' => 4,
      '#maxlength' => 4,
      '#default_value' => isset($items[$delta]->year) ? $items[$delta]->year : '',
    ];

    $element['month'] = [
      '#type' => 'number',
      '#title' => t('Month'),
      '#min' => 1,
      '#max' => 12,
      '#size' => 5,
      '#default_value' => isset($items[$delta]->month) ? $items[$delta]->month : NULL,
    ];

    $element['day'] = [
      '#type' => 'number',
      '#title' => t('Day'),
      '#min' => 1,
      '#max' => 31,
      '#size' => 5,
      '#default_value' => isset($items[$delta]->day) ? $items[$delta]->day : NULL,
    ];

    $element += [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['container-inline']],
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    // In case of leading zeros.
    $year = ltrim($element['year']['#value'], "0");
    $month = (int) ltrim($element['month']['#value'], "0");
    $day = (int) ltrim($element['day']['#value'], "0");

    // If empty, return.
    if (!$year && !$month && !$day) {
      $form_state->setValueForElement($element, '');
      return;
    }

    // 1. ensure date in acceptable format.
    if ($year && $month && $day || $year && $month && !$day || $year && !$month && !$day) {
      // 2. Ensure date is valid.
      if (!checkdate(($month == 0 ? 1 : $month), ($day == 0 ? 1 : $day), $year)) {
        $form_state->setError($element, "Invalid date.");
      }
    }
    else {
      $form_state->setError($element, "Acceptable date formats are year only, year/month, or year/month/day.");
    }
  }

}

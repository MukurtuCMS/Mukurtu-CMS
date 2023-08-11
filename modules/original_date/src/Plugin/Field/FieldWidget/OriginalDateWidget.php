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

      $values[$key]['year'] = $year;
      $values[$key]['month'] = $month;
      $values[$key]['day'] = $day;
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
      '#type' => 'textfield',
      '#title' => t('Month'),
      '#size' => 2,
      '#maxlength' => 2,
      '#default_value' => isset($items[$delta]->month) ? $items[$delta]->month : '',
    ];

    $element['day'] = [
      '#type' => 'textfield',
      '#title' => t('Day'),
      '#size' => 2,
      '#maxlength' => 2,
      '#default_value' => isset($items[$delta]->day) ? $items[$delta]->day : '',
    ];

    $element += [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $element['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Acceptable date formats are YYYY, YYYY-MM, or YYYY-MM-DD.'),
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    $year = $element['year']['#value'];
    $month = $element['month']['#value'];
    $day = $element['day']['#value'];

    // If empty, return.
    if (!$year && !$month && !$day) {
      $form_state->setValueForElement($element, '');
      return;
    }

    // Ensure date in acceptable format.
    if ($year && $month && $day || $year && $month && !$day || $year && !$month && !$day) {
      $year = intval($year);
      $month = $month == '' ? 1 : intval($month);
      $day = $day == '' ? 1 : intval($day);

      // Ensure date is valid.
      if (!checkdate($month, $day, $year)) {
        $form_state->setError($element, "Invalid date.");
      }
    }
    else {
      $form_state->setError($element, "Acceptable date formats are YYYY, YYYY-MM, or YYYY-MM-DD.");
    }
  }
}

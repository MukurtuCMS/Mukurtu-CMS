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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#element_validate' => [
        [$this, 'validate'],
      ]
    ];

    $element['year'] = [
      '#type' => 'number',
      '#title' => t('Year'),
      '#min' => 1,
      # The max year is set to comply with PHP's checkdate().
      '#max' => 32767,
      '#default_value' => isset($items[$delta]->year) ? $items[$delta]->year : NULL,
    ];

    // Set options for allowed months.
    $monthOptions = [];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    foreach (range(0, 11) as $i) {
      $monthOptions += [strval($i + 1) => $this->t(strval($i + 1) . '-' . $months[$i])];
    }

    $element['month'] = [
      '#type' => 'select',
      '#title' => t('Month'),
      '#options' => $monthOptions,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => isset($items[$delta]->month) ? $items[$delta]->month : NULL,
    ];

    // Set options for allowed days.
    $dayOptions = [];
    foreach (range(1, 31) as $i) {
      $dayOptions += [strval($i) => $this->t(strval($i))];
    }

    $element['day'] = [
      '#type' => 'select',
      '#title' => t('Day'),
      '#options' => $dayOptions,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => isset($items[$delta]->day) ? $items[$delta]->day : NULL,
    ];

    $element += [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $element['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t(''),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    $year = $element['year']['#value'];
    $month = $element['month']['#value'];
    $day = $element['day']['#value'];

    // If empty, return.
    if ($year == '' && $month == '' && $day == '') {
      $form_state->setValueForElement($element, NULL);
      return;
    }

    // Ensure date in acceptable format.
    if ($year != '' && $month != '' && $day != '' || $year != '' && $month != '' && $day == '' || $year != '' && $month == '' && $day == '') {
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

<?php

namespace Drupal\original_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'textual_month_day_year_original_date_formatter'
 * formatter.
 *
 * @FieldFormatter(
 *   id = "textual_month_day_year_original_date_formatter",
 *   module = "original_date",
 *   description = "Displays original date in Month, Day Year format.",
 *   label = @Translation("Month Day, Year Original Date Formatter"),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateFormatterTextualMonthDayYear extends FormatterBase
{
  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];
    $summary[] = $this->t('Displays the original date in Month Day, Year format (e.g. January 3, 2015).');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $element = [];
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    foreach ($items as $delta => $item) {
      $monthText = $months[intval($item->month) - 1];
      $dateTemplate = "@month @day, @year";
      $dateStr = $this->t($dateTemplate, [
        '@month' => $monthText,
        '@day' => $item->day,
        '@year' => $item->year
      ]);
      $element[$delta] = ['#markup' => $dateStr];
    }

    return $element;
  }
}

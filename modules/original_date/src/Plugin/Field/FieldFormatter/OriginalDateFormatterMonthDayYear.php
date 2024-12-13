<?php

namespace Drupal\original_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'month_day_year_original_date_formatter'
 * formatter. Supports partial dates for "Month, Year" and "Year".
 *
 * @FieldFormatter(
 *   id = "month_day_year_original_date_formatter",
 *   module = "original_date",
 *   description = "Displays Original Date in Month, Day Year format.",
 *   label = @Translation("Original Date display: Month Day, Year"),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateFormatterMonthDayYear extends FormatterBase
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
      // Check for partial dates. There are only two allowed cases.
      // Check if date should be "Month Year".
      if ($item->year && $item->month && !$item->day) {
        $dateTemplate = "@month @year";
        $monthText = $months[intval($item->month) - 1];
        $dateStr = $this->t($dateTemplate, [
          '@month' => $monthText,
          '@year' => $item->year
        ]);
      }
      // Check if date should be "Year".
      else if ($item->year && !$item->month && !$item->day) {
        $dateTemplate = "@year";
        $dateStr = $this->t($dateTemplate, ['@year' => $item->year]);
      }
      // Date is full.
      else {
        $dateTemplate = "@month @day, @year";
        $monthText = $months[intval($item->month) - 1];
        $dateStr = $this->t($dateTemplate, [
          '@month' => $monthText,
          '@day' => $item->day,
          '@year' => $item->year
        ]);
      }

      $element[$delta] = ['#markup' => $dateStr];
    }

    return $element;
  }
}

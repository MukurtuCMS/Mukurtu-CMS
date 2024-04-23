<?php

namespace Drupal\original_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'year_month_day_original_date_formatter'
 * formatter.
 *
 * @FieldFormatter(
 *   id = "year_month_day_original_date_formatter",
 *   module = "original_date",
 *   description = "Displays original date in YYYY-MM-DD format.",
 *   label = @Translation("YYYY-MM-DD Original Date Formatter"),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateFormatterYearMonthDay extends FormatterBase
{
  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];
    $summary[] = $this->t('Displays the original date in YYYY-MM-DD format (e.g. 2015-08-20).');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = ['#markup' => $item->date];
    }

    return $element;
  }
}

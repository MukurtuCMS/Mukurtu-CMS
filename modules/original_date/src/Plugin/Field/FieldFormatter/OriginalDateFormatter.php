<?php

namespace Drupal\original_date\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'original_date_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "original_date_formatter",
 *   module = "original_date",
 *   label = @Translation("Original Date Formatter"),
 *   field_types = {
 *     "original_date"
 *   }
 * )
 */
class OriginalDateFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $summary = [];
        $summary[] = $this->t('Displays the original date.');
        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $element = [];

        foreach ($items as $delta => $item) {
            // Render each element as markup.
            $element[$delta] = ['#markup' => $item->value];
        }

        return $element;
    }
}

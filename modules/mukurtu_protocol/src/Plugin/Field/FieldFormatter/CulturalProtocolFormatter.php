<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'cultural_protocol' formatter.
 *
 * @FieldFormatter(
 *   id = "cultural_protocol_formatter",
 *   label = @Translation("Cultural Protocol Formatter"),
 *   field_types = {
 *     "cultural_protocol"
 *   }
 * )
 */
class CulturalProtocolFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = ['#markup' => $item->value];
    }

    return $element;
  }

}

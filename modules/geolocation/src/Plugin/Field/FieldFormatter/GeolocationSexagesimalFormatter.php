<?php

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'geolocation_sexagesimal' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_sexagesimal",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Sexagesimal / GPS / DMS"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationSexagesimalFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#theme' => 'geolocation_sexagesimal_formatter',
        '#lat' => $item::decimalToSexagesimal($item->lat),
        '#lng' => $item::decimalToSexagesimal($item->lng),
      ];
    }

    return $element;
  }

}

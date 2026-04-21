<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldFormatter;

use Drupal\color_field\ColorHex;
use Drupal\color_field\Plugin\Field\FieldType\ColorFieldType;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Template\Attribute;

/**
 * Plugin implementation of the color_field swatch formatter.
 *
 * @FieldFormatter(
 *   id = "color_field_formatter_swatch_options",
 *   module = "color_field",
 *   label = @Translation("Color swatch options"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldFormatterSwatchOptions extends ColorFieldFormatterSwatch {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $settings = $this->getSettings();

    $elements = [];

    $name = Html::getUniqueId("color-field");

    foreach ($items as $delta => $item) {
      $hex = $this->viewRawValue($item);
      $id = Html::getUniqueId("color-field-$hex");
      $elements[$delta] = [
        '#theme' => 'color_field_formatter_swatch_option',
        '#id' => $id,
        '#name' => $name,
        '#input_type' => $this->fieldDefinition->getFieldStorageDefinition()->isMultiple() ? 'checkbox' : 'radio',
        '#value' => $hex,
        '#shape' => $settings['shape'],
        '#height' => is_numeric($settings['height']) ? "{$settings['height']}px" : $settings['height'],
        '#width' => is_numeric($settings['width']) ? "{$settings['width']}px" : $settings['width'],
        '#color' => $this->viewValue($item),
        '#attributes' => new Attribute([
          'class' => [
            "color_field__swatch--{$settings['shape']}",
          ],
        ]),
      ];

      if (!$settings['data_attribute']) {
        continue;
      }

      $elements[$delta]['#attributes']['data-color'] = $hex;
    }

    return $elements;
  }

  /**
   * Return the raw field value.
   *
   * @param \Drupal\color_field\Plugin\Field\FieldType\ColorFieldType $item
   *   The color field item.
   *
   * @return string
   *   The color hex value.
   */
  protected function viewRawValue(ColorFieldType $item): string {
    return (new ColorHex($item->color, is_null($item->opacity) ? NULL : (float) $item->opacity))->toString(FALSE);
  }

}

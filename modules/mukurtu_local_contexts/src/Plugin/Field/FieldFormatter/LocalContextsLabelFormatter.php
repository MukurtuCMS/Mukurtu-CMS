<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\mukurtu_local_contexts\LocalContextsLabel;

/**
 * Plugin implementation of the 'Local Contexts Label' formatter.
 *
 * @FieldFormatter(
 *   id = "local_contexts_label",
 *   label = @Translation("Local Contexts Label"),
 *   field_types = {
 *     "local_contexts_label"
 *   }
 * )
 */
class LocalContextsLabelFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $label = new LocalContextsLabel($item->value);
      dpm($label);

      $element[$delta] = [
        '#theme' => 'local_contexts_label',
        '#name' => $label->name,
        '#text' => $label->default_text,
        '#svg_url' => $label->svg_url,
        '#locale' => $label->locale,
        '#language' => $label->language,
        '#translationName' => $label->translationName,
        '#translationText' => $label->translationText,
      ];
    }

    return $element;
  }

}

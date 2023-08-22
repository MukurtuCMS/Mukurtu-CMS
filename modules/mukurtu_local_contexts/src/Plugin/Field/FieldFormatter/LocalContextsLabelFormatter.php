<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\mukurtu_local_contexts\LocalContextsLabel;
use Drupal\mukurtu_local_contexts\LocalContextsNotice;

/**
 * Plugin implementation of the 'Local Contexts Label and Notice' formatter.
 *
 * @FieldFormatter(
 *   id = "local_contexts_label_and_notice",
 *   label = @Translation("Local Contexts Label and Notice"),
 *   field_types = {
 *     "local_contexts_label_and_notice"
 *   }
 * )
 */
class LocalContextsLabelFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $first = '';
    $second = '';

    foreach ($items as $delta => $item) {
      list($first, $second, $third) = explode(':', $item->value);
      // $item->value = project_id:label_id_or_notice_type:label_or_notice.

      // Check if third value in the $item->value throuple is a notice or label.
      if ($third == 'notice') {
        $notice = new LocalContextsNotice($item->value);
        $element[$delta] = [
          '#theme' => 'local_contexts_labels_and_notices',
          '#name' => $notice->name,
          '#text' => $notice->default_text,
          '#svg_url' => $notice->svg_url,
          '#translations' => $notice->translations,
        ];
      }
      else if ($third == 'label') {
        $label = new LocalContextsLabel($item->value);
        $element[$delta] = [
          '#theme' => 'local_contexts_labels_and_notices',
          '#name' => $label->name,
          '#text' => $label->default_text,
          '#svg_url' => $label->svg_url,
          '#translations' => $label->translations,
        ];
      }
    }

    return $element;
  }

}

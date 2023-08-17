<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\mukurtu_local_contexts\LocalContextsLabel;
use Drupal\mukurtu_local_contexts\LocalContextsNotice;

/**
 * Plugin implementation of the 'Local Contexts Labels and Notices' formatter.
 *
 * @FieldFormatter(
 *   id = "local_contexts_labels",
 *   label = @Translation("Local Contexts Labels and Notices"),
 *   field_types = {
 *     "local_contexts_labels"
 *   }
 * )
 */
class LocalContextsLabelFormatter extends FormatterBase {
  protected $noticeTypes;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->noticeTypes = ['traditional_knowledge', 'biocultural', 'attribution_incomplete', 'open_to_collaborate'];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $first = '';
    $second = '';

    foreach ($items as $delta => $item) {
      list($first, $second) = explode(':', $item->value);
      // $item->value = project_id:label_id_or_notice_type.

      // Check if the second value after ':' in $item->value is a notice type.
      // If so, the item is a notice and we must handle it differently.
      if (in_array($second, $this->noticeTypes)) {
        $notice = new LocalContextsNotice($item->value);
        $element[$delta] = [
          '#theme' => 'local_contexts_labels',
          '#name' => $notice->name,
          '#text' => $notice->default_text,
          '#svg_url' => $notice->svg_url,
          '#locale' => $notice->locale,
          '#language' => $notice->language,
          '#translationName' => $notice->translationName,
          '#translationText' => $notice->translationText,
        ];
      }
      else {
        $label = new LocalContextsLabel($item->value);
        $element[$delta] = [
          '#theme' => 'local_contexts_labels',
          '#name' => $label->name,
          '#text' => $label->default_text,
          '#svg_url' => $label->svg_url,
          '#locale' => $label->locale,
          '#language' => $label->language,
          '#translationName' => $label->translationName,
          '#translationText' => $label->translationText,
        ];
      }
    }

    return $element;
  }

}

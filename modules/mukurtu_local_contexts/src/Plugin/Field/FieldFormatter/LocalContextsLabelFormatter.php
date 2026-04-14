<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Component\Utility\Html;
use Drupal\mukurtu_local_contexts\LocalContextsLabel;
use Drupal\mukurtu_local_contexts\LocalContextsNotice;
use Drupal\mukurtu_local_contexts\LocalContextsProject;

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
    $grouped = [];

    foreach ($items as $item) {
      [$project_id, , $third] = explode(':', $item->value);

      if ($third === 'label') {
        $label = new LocalContextsLabel($item->value);
        $grouped[$project_id][] = [
          '#theme' => 'local_contexts_label',
          '#name' => $label->name,
          '#text' => $label->default_text,
          '#svg_url' => $label->svg_url,
          '#img_url' => $label->img_url,
          '#audio_url' => $label->audio_url,
          '#community' => $label->community,
          '#translations' => $label->translations,
          '#dialog_id' => Html::getUniqueId('lc-label'),
        ];
      }
      elseif ($third === 'notice') {
        $notice = new LocalContextsNotice($item->value);
        $grouped[$project_id][] = [
          '#theme' => 'local_contexts_notice',
          '#name' => $notice->name,
          '#text' => $notice->default_text,
          '#svg_url' => $notice->svg_url,
          '#img_url' => $notice->img_url,
          '#translations' => $notice->translations,
          '#dialog_id' => Html::getUniqueId('lc-notice'),
        ];
      }
    }

    $element = [];
    $delta = 0;
    foreach ($grouped as $project_id => $group_items) {
      $project = new LocalContextsProject($project_id);
      $project_title = $project->isValid() ? $project->getTitle() : null;
      $project_url = $project->isValid() ? $project->getUrl() : null;
      $group_items = array_map(function ($item) use ($project_title, $project_url) {
        $item['#project_title'] = $project_title;
        $item['#project_url'] = $project_url;
        return $item;
      }, $group_items);
      $element[$delta] = [
        '#theme' => 'local_contexts_label_group',
        '#project_title' => $project_title,
        '#items' => $group_items,
      ];
      $delta++;
    }

    return $element;
  }

}

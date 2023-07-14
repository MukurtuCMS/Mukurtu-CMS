<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Plugin implementation of the 'Local Contexts Project' formatter.
 *
 * @FieldFormatter(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project"),
 *   field_types = {
 *     "local_contexts_project"
 *   }
 * )
 */
class LocalContextsProjectFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $project = new LocalContextsProject($item->value);
      if (!$project->isValid()) {
        continue;
      }
      $element[$delta] = [
        '#theme' => 'local_contexts_project',
        '#title' => $project->getTitle(),
        '#tk_labels' => $project->getTkLabels(),
      ];
    }

    return $element;
  }

}

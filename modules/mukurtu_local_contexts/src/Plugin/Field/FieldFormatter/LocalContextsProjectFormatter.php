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
      $projectUrl = $project->getUrl();

      $labelItems = array_map(
        fn (array $label) => LocalContextsProject::buildLabelRenderArray($label, $project->getTitle(), $projectUrl),
        array_merge($project->getLabels("tk"), $project->getLabels("bc"))
      );
      $noticeItems = array_map(
        fn (array $notice) => LocalContextsProject::buildNoticeRenderArray($notice, $project->getTitle(), $projectUrl),
        $project->getNotices()
      );

      $element[$delta] = [
        '#theme' => 'local_contexts_label_group',
        '#project_title' => $project->getTitle(),
        '#project_url' => $projectUrl,
        '#items' => array_merge($labelItems, $noticeItems),
        '#not_available' => $project->isNotAvailable(),
        '#archived' => $project->isArchived(),
        '#last_synced' => $project->getUpdated(),
      ];
    }

    return $element;
  }

}

<?php

namespace Drupal\mukurtu_local_contexts\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Strips references to a deleted Local Contexts project from a node.
 *
 * Enqueued by LocalContextsSupportedProjectManager::queueReferenceRemoval()
 * when a project is confirmed deleted from the hub and purged. Only the
 * specific delta(s) matching the deleted project are removed from each
 * field - the rest of the field's value is left untouched.
 *
 * Field values are set directly via the entity API rather than through the
 * widget/form pipeline. This matters: core's
 * OptionsWidgetBase::getSelectedOptions() silently drops any current field
 * value that isn't present in the widget's options list, which would
 * happen here anyway since the project's cache rows are already gone by
 * the time this runs - but going through the entity API directly keeps
 * that behavior explicit and intentional rather than an accidental side
 * effect of a form save.
 */
#[QueueWorker(
  id: 'mukurtu_local_contexts_reference_cleanup',
  title: new TranslatableMarkup('Local Contexts reference cleanup'),
  cron: ['time' => 30],
)]
class LocalContextsReferenceCleanupWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($data['nid']);
    if (!$node) {
      // The node was deleted since this item was queued - nothing to do.
      return;
    }

    $changed = FALSE;

    if ($node->hasField('field_local_contexts_projects')) {
      $values = array_column($node->get('field_local_contexts_projects')->getValue(), 'value');
      $filtered = array_values(array_filter($values, fn($value) => $value !== $data['project_id']));
      if (count($filtered) !== count($values)) {
        $node->set('field_local_contexts_projects', $filtered);
        $changed = TRUE;
      }
    }

    if (!empty($data['label_and_notice_values']) && $node->hasField('field_local_contexts_labels_and_notices')) {
      $values = array_column($node->get('field_local_contexts_labels_and_notices')->getValue(), 'value');
      $filtered = array_values(array_diff($values, $data['label_and_notice_values']));
      if (count($filtered) !== count($values)) {
        $node->set('field_local_contexts_labels_and_notices', $filtered);
        $changed = TRUE;
      }
    }

    if ($changed) {
      if ($node->getEntityType()->isRevisionable()) {
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage('Removed reference to a Local Contexts project that was deleted from the Local Contexts Hub.');
      }
      $node->save();
    }
  }

}

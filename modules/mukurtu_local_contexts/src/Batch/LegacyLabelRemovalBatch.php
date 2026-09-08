<?php

namespace Drupal\mukurtu_local_contexts\Batch;

/**
 * Batch operation for removing a specific legacy label/notice from an
 * admin-selected set of content, without replacing it with anything.
 */
class LegacyLabelRemovalBatch {

  /**
   * Number of nodes processed per batch pass.
   */
  const BATCH_SIZE = 25;

  /**
   * Batch operation callback.
   *
   * @param string $projectId
   *   The legacy project ID.
   * @param string $refType
   *   Either 'label' or 'notice'.
   * @param string $refId
   *   The label ID or notice type.
   * @param array $nids
   *   The admin-selected node IDs to remove the reference from.
   * @param array $context
   *   The batch context.
   */
  public static function run(string $projectId, string $refType, string $refId, array $nids, array &$context) {
    if (!isset($context['sandbox']['nids'])) {
      $context['sandbox']['nids'] = array_values(array_unique($nids));
      $context['sandbox']['max'] = count($context['sandbox']['nids']);
      $context['sandbox']['progress'] = 0;
      $context['results'] += [
        'project_id' => $projectId,
        'ref_type' => $refType,
        'ref_id' => $refId,
        'removed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'error_log' => [],
      ];
    }

    if ($context['sandbox']['max'] === 0) {
      $context['finished'] = 1;
      return;
    }

    $chunk = array_splice($context['sandbox']['nids'], 0, self::BATCH_SIZE);
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $targetValue = $projectId . ':' . $refId . ':' . $refType;

    foreach ($chunk as $nid) {
      try {
        $node = $storage->load($nid);
        if (!$node || !$node->hasField('field_local_contexts_labels_and_notices')) {
          $context['sandbox']['progress']++;
          continue;
        }

        $found = FALSE;
        $remaining = [];
        foreach ($node->get('field_local_contexts_labels_and_notices') as $item) {
          if ((string) $item->value === $targetValue) {
            $found = TRUE;
            continue;
          }
          $remaining[] = $item->value;
        }

        if ($found) {
          $node->set('field_local_contexts_labels_and_notices', $remaining);
          $node->setNewRevision(TRUE);
          $node->revision_log = t('Removed legacy Local Contexts label/notice @value from content.', ['@value' => $targetValue]);
          $node->save();
          $context['results']['removed']++;
        }
        else {
          $context['results']['skipped']++;
        }
      }
      catch (\Throwable $e) {
        $context['results']['errors']++;
        $context['results']['error_log'][] = "Node {$nid}: " . $e->getMessage();
        \Drupal::logger('mukurtu_local_contexts')->error('Legacy label removal failed for node @nid: @message', [
          '@nid' => $nid,
          '@message' => $e->getMessage(),
        ]);
      }
      $context['sandbox']['progress']++;
    }

    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed without a fatal error.
   * @param array $results
   *   The accumulated results from run().
   */
  public static function finished($success, array $results) {
    $messenger = \Drupal::messenger();

    if (!$success) {
      $messenger->addError(t('The legacy label removal did not complete successfully.'));
      return;
    }

    if (empty($results['removed']) && empty($results['skipped']) && empty($results['errors'])) {
      $messenger->addStatus(t('Nothing to remove — the selected items no longer referenced this label.'));
      return;
    }

    if (!empty($results['removed'])) {
      $messenger->addStatus(\Drupal::translation()->formatPlural(
        $results['removed'],
        '1 item was updated.',
        '@count items were updated.'
      ));
    }

    if (!empty($results['skipped'])) {
      $messenger->addWarning(\Drupal::translation()->formatPlural(
        $results['skipped'],
        '1 item no longer referenced this label and was left as-is.',
        '@count items no longer referenced this label and were left as-is.'
      ));
    }

    if (!empty($results['errors'])) {
      $messenger->addError(\Drupal::translation()->formatPlural(
        $results['errors'],
        '1 item failed to update; see the logs for details.',
        '@count items failed to update; see the logs for details.'
      ));
    }
  }

}

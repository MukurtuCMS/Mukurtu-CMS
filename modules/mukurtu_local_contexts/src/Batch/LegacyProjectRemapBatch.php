<?php

namespace Drupal\mukurtu_local_contexts\Batch;

use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Batch operation for reassigning content off a legacy Local Contexts project.
 */
class LegacyProjectRemapBatch {

  /**
   * Number of nodes processed per batch pass.
   */
  const BATCH_SIZE = 25;

  /**
   * Batch operation callback.
   *
   * @param string $legacyProjectId
   *   The legacy project ID being reassigned.
   * @param string $targetProjectId
   *   The real Hub project ID to reassign to.
   * @param array $labelMapping
   *   Legacy label ID/notice type => target label ID/notice type.
   * @param array $context
   *   The batch context.
   */
  public static function run(string $legacyProjectId, string $targetProjectId, array $labelMapping, array &$context) {
    if (!isset($context['sandbox']['nids'])) {
      $legacy = new LocalContextsProject($legacyProjectId);
      $referencing = $legacy->getReferencingNodeIds();

      $mappedNids = [];
      foreach ($labelMapping as $legacyId => $targetLabelId) {
        $mappedNids = array_merge($mappedNids, $referencing['labels_and_notices'][$legacyId] ?? []);
      }

      $context['sandbox']['nids'] = array_values(array_unique(array_merge($referencing['project'], $mappedNids)));
      $context['sandbox']['max'] = count($context['sandbox']['nids']);
      $context['sandbox']['progress'] = 0;
      $context['results'] += [
        'legacy_project_id' => $legacyProjectId,
        'rewritten' => 0,
        'errors' => 0,
        'error_log' => [],
        'skipped_by_id' => [],
      ];
    }

    if ($context['sandbox']['max'] === 0) {
      $context['finished'] = 1;
      return;
    }

    $chunk = array_splice($context['sandbox']['nids'], 0, self::BATCH_SIZE);
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($chunk as $nid) {
      try {
        $node = $storage->load($nid);
        if (!$node) {
          $context['sandbox']['progress']++;
          continue;
        }
        $changed = FALSE;

        if ($node->hasField('field_local_contexts_projects')) {
          foreach ($node->get('field_local_contexts_projects') as $item) {
            if ($item->value === $legacyProjectId) {
              $item->value = $targetProjectId;
              $changed = TRUE;
            }
          }
        }

        if ($node->hasField('field_local_contexts_labels_and_notices')) {
          foreach ($node->get('field_local_contexts_labels_and_notices') as $item) {
            $parts = explode(':', (string) $item->value, 3);
            if (count($parts) !== 3) {
              continue;
            }
            [$projectId, $labelOrType, $display] = $parts;
            if ($projectId !== $legacyProjectId) {
              continue;
            }
            if (isset($labelMapping[$labelOrType])) {
              $item->value = $targetProjectId . ':' . $labelMapping[$labelOrType] . ':' . $display;
              $changed = TRUE;
            }
            else {
              $context['results']['skipped_by_id'][$labelOrType] = ($context['results']['skipped_by_id'][$labelOrType] ?? 0) + 1;
            }
          }
        }

        if ($changed) {
          $node->setNewRevision(TRUE);
          $node->revision_log = t('Reassigned legacy Local Contexts project @legacy to @target.', [
            '@legacy' => $legacyProjectId,
            '@target' => $targetProjectId,
          ]);
          $node->save();
          $context['results']['rewritten']++;
        }
      }
      catch (\Throwable $e) {
        $context['results']['errors']++;
        $context['results']['error_log'][] = "Node {$nid}: " . $e->getMessage();
        \Drupal::logger('mukurtu_local_contexts')->error('Legacy project remap failed for node @nid: @message', [
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
      $messenger->addError(t('The legacy project reassignment did not complete successfully.'));
      return;
    }

    if (!empty($results['rewritten'])) {
      $messenger->addStatus(\Drupal::translation()->formatPlural(
        $results['rewritten'],
        '1 node was updated.',
        '@count nodes were updated.'
      ));
    }

    if (!empty($results['skipped_by_id'])) {
      $legacy = new LocalContextsProject($results['legacy_project_id']);
      $names = [];
      foreach (array_merge($legacy->getLabels('tk'), $legacy->getLabels('bc')) as $id => $label) {
        $names[$id] = $label['name'];
      }
      foreach ($legacy->getNotices() as $notice) {
        $names[$notice['notice_type']] = $notice['name'];
      }

      $lines = [];
      foreach ($results['skipped_by_id'] as $id => $count) {
        $lines[] = ($names[$id] ?? $id) . " ({$count})";
      }
      $messenger->addWarning(t('Some content still references unmapped labels/notices and was left as-is: @list', [
        '@list' => implode(', ', $lines),
      ]));
    }

    if (!empty($results['errors'])) {
      $messenger->addError(\Drupal::translation()->formatPlural(
        $results['errors'],
        '1 node failed to update; see the logs for details.',
        '@count nodes failed to update; see the logs for details.'
      ));
    }
  }

}

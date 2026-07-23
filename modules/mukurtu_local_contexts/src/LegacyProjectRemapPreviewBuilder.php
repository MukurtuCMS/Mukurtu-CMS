<?php

namespace Drupal\mukurtu_local_contexts;

/**
 * Computes the affected-content breakdown for a legacy project remap.
 */
class LegacyProjectRemapPreviewBuilder {

  /**
   * Build the preview breakdown for a proposed legacy project remap.
   *
   * @param string $legacyProjectId
   *   The legacy project ID being reassigned.
   * @param string $targetProjectId
   *   The real Hub project ID content will be reassigned to (unused for the
   *   breakdown itself, kept for a consistent signature with the batch).
   * @param array $labelMapping
   *   An array keyed by legacy label ID or notice type, each value the
   *   real label ID or notice type on the target project to map to.
   *
   * @return array
   *   An array with keys:
   *   - 'rows': a list of rows, each an array with 'id' (NULL for the
   *     whole-project row), 'label' (display name), 'nids' (int[]), and
   *     'mapped' (bool, always TRUE for the whole-project row).
   *   - 'total': int, the count of distinct nodes that will actually be
   *     updated - the whole-project row plus any mapped rows. Unmapped rows
   *     are informational only and excluded from this count. This is a
   *     deduplicated union, not a sum of row counts, since a single node can
   *     be referenced more than one way at once.
   */
  public function build(string $legacyProjectId, string $targetProjectId, array $labelMapping): array {
    $legacy = new LocalContextsProject($legacyProjectId);
    $referencing = $legacy->getReferencingNodeIds();

    $names = [];
    foreach (array_merge($legacy->getLabels('tk'), $legacy->getLabels('bc')) as $id => $label) {
      $names[$id] = $label['name'];
    }
    foreach ($legacy->getNotices() as $notice) {
      $names[$notice['notice_type']] = $notice['name'];
    }

    $rows = [];
    $updateNids = [];

    if (!empty($referencing['project'])) {
      $rows[] = [
        'id' => NULL,
        'label' => NULL,
        'nids' => $referencing['project'],
        'mapped' => TRUE,
      ];
      $updateNids = array_merge($updateNids, $referencing['project']);
    }

    foreach ($referencing['labels_and_notices'] as $id => $nids) {
      $mapped = isset($labelMapping[$id]);
      $rows[] = [
        'id' => $id,
        'label' => $names[$id] ?? $id,
        'nids' => $nids,
        'mapped' => $mapped,
      ];
      if ($mapped) {
        $updateNids = array_merge($updateNids, $nids);
      }
    }

    return [
      'rows' => $rows,
      'total' => count(array_unique($updateNids)),
    ];
  }

}

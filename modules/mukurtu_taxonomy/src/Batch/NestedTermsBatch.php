<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Batch;

/**
 * Batch operations that make a vocabulary flat or restore its nesting.
 *
 * @see \Drupal\mukurtu_taxonomy\Form\FlattenVocabularyConfirmForm
 * @see \Drupal\mukurtu_taxonomy\Form\RestoreNestingConfirmForm
 */
class NestedTermsBatch {

  /**
   * Number of terms saved per batch pass.
   */
  const BATCH_SIZE = 50;

  /**
   * Batch operation: moves nested terms to the top level.
   *
   * Weights are kept, so restoring the snapshot later puts each term back in
   * its old place among its siblings.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param int[] $tids
   *   IDs of the nested terms to move.
   * @param array $context
   *   The batch context.
   */
  public static function flatten(string $vid, array $tids, array &$context): void {
    if (!isset($context['sandbox']['tids'])) {
      $context['sandbox']['tids'] = array_values($tids);
      $context['sandbox']['max'] = count($tids);
      $context['sandbox']['progress'] = 0;
      $context['results'] += ['vid' => $vid, 'moved' => 0, 'errors' => 0];
    }

    if ($context['sandbox']['max'] === 0) {
      $context['finished'] = 1;
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach (array_splice($context['sandbox']['tids'], 0, self::BATCH_SIZE) as $tid) {
      try {
        $term = $storage->load($tid);
        if ($term && $term->bundle() === $vid && (int) $term->get('parent')->target_id !== 0) {
          $term->set('parent', 0);
          $term->save();
          $context['results']['moved']++;
        }
      }
      catch (\Throwable $e) {
        $context['results']['errors']++;
        \Drupal::logger('mukurtu_taxonomy')->error('Could not move term @tid to the top level: @message', [
          '@tid' => $tid,
          '@message' => $e->getMessage(),
        ]);
      }
      $context['sandbox']['progress']++;
    }

    $context['message'] = t('Moved @current of @total terms to the top level.', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['max'],
    ]);
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch finished callback for flatten().
   *
   * Makes the vocabulary flat only if every term moved. Otherwise it stays
   * nested, and its snapshot is still there to restore from.
   */
  public static function flattenFinished(bool $success, array $results): void {
    // Saved below, so it's loaded without translations, which would otherwise
    // be saved as the original values.
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadOverrideFree($results['vid'] ?? '');
    if (!$vocabulary) {
      return;
    }
    $label = \Drupal::service('mukurtu_taxonomy.nested_terms')->label($vocabulary->id());

    if (!$success || !empty($results['errors'])) {
      \Drupal::messenger()->addError(t("Some terms in %vocabulary couldn't be moved to the top level, so the vocabulary still allows nested terms. See the recent log messages for details.", [
        '%vocabulary' => $label,
      ]));
      return;
    }

    $vocabulary->setThirdPartySetting('flat_taxonomy', 'flat', 1);
    $vocabulary->save();

    \Drupal::messenger()->addStatus(\Drupal::translation()->formatPlural(
      $results['moved'] ?? 0,
      '%vocabulary is now flat. 1 term moved to the top level.',
      '%vocabulary is now flat. @count terms moved to the top level.',
      ['%vocabulary' => $label]
    ));
  }

  /**
   * Batch operation: puts terms back under their saved parents.
   *
   * Terms deleted since the snapshot are skipped. A term whose saved parent
   * no longer exists stays at the top level.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param array $snapshot
   *   Parent and weight, keyed by term ID.
   * @param array $context
   *   The batch context.
   */
  public static function restore(string $vid, array $snapshot, array &$context): void {
    if (!isset($context['sandbox']['snapshot'])) {
      // A list, not keyed by term ID: array_splice() renumbers integer keys.
      $context['sandbox']['snapshot'] = [];
      foreach ($snapshot as $tid => $saved) {
        $context['sandbox']['snapshot'][] = ['tid' => $tid] + $saved;
      }
      $context['sandbox']['max'] = count($snapshot);
      $context['sandbox']['progress'] = 0;
      $context['results'] += ['vid' => $vid, 'restored' => 0, 'orphaned' => 0, 'errors' => 0];
    }

    if ($context['sandbox']['max'] === 0) {
      $context['finished'] = 1;
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach (array_splice($context['sandbox']['snapshot'], 0, self::BATCH_SIZE) as $saved) {
      $tid = $saved['tid'];
      $context['sandbox']['progress']++;
      try {
        $term = $storage->load($tid);
        if (!$term || $term->bundle() !== $vid) {
          continue;
        }
        $parent = $saved['parent'] ? $storage->load($saved['parent']) : NULL;
        if ($saved['parent'] && (!$parent || $parent->bundle() !== $vid)) {
          $context['results']['orphaned']++;
          continue;
        }
        $term->set('parent', $saved['parent']);
        $term->setWeight($saved['weight']);
        $term->save();
        $context['results']['restored']++;
      }
      catch (\Throwable $e) {
        $context['results']['errors']++;
        \Drupal::logger('mukurtu_taxonomy')->error('Could not restore the parent of term @tid: @message', [
          '@tid' => $tid,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $context['message'] = t('Restored @current of @total terms.', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['max'],
    ]);
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch finished callback for restore().
   *
   * Deletes the snapshot only if every term was handled, so a failed restore
   * can be tried again.
   */
  public static function restoreFinished(bool $success, array $results): void {
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($results['vid'] ?? '');
    if (!$vocabulary) {
      return;
    }
    $label = \Drupal::service('mukurtu_taxonomy.nested_terms')->label($vocabulary->id());

    if (!$success || !empty($results['errors'])) {
      \Drupal::messenger()->addError(t("Some terms in %vocabulary couldn't be restored. The saved nesting is kept, so you can try again the next time you allow nested terms. See the recent log messages for details.", [
        '%vocabulary' => $label,
      ]));
      return;
    }

    \Drupal::service('mukurtu_taxonomy.nested_terms')->deleteSnapshot($vocabulary->id());

    $translation = \Drupal::translation();
    \Drupal::messenger()->addStatus($translation->formatPlural(
      $results['restored'] ?? 0,
      'Restored the nesting for 1 term in %vocabulary.',
      'Restored the nesting for @count terms in %vocabulary.',
      ['%vocabulary' => $label]
    ));
    if (!empty($results['orphaned'])) {
      \Drupal::messenger()->addWarning($translation->formatPlural(
        $results['orphaned'],
        '1 term stayed at the top level because its previous parent no longer exists.',
        '@count terms stayed at the top level because their previous parent no longer exists.'
      ));
    }
  }

}

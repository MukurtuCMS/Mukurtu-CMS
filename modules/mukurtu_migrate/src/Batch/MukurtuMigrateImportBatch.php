<?php

namespace Drupal\mukurtu_migrate\Batch;

class MukurtuMigrateImportBatch {
  /**
   * Callback executed when the Mukurtu migrate batch process completes.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of methods run in the batch.
   * @param string $elapsed
   *   The time to run the batch.
   */
  public static function finished($success, $results, $operations, $elapsed) {
    $successes = $results['successes'];
    $failures = $results['failures'];

    if ($successes > 0) {
      \Drupal::messenger()->addStatus(\Drupal::translation()
        ->formatPlural($successes, 'Completed 1 migration task successfully', 'Completed @count migration tasks successfully'));
    }
    if ($failures > 0) {
      \Drupal::messenger()->addError(\Drupal::translation()
        ->formatPlural($failures, '1 migration failed', '@count migrations failed'));
      \Drupal::messenger()->addError(t('Migration process not completed'));
    } else {
      \Drupal::messenger()->addStatus(t('Migration from Mukurtu CMS version 3 was successful.'));
    }
  }

}

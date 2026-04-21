<?php

declare(strict_types=1);

namespace Drupal\genpass_batch\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Hook user insert and run batch job on it.
 */
class UserInsertBatch {

  use StringTranslationTrait;

  /**
   * Initiate batch job on user insert.
   */
  #[Hook('user_insert')]
  public function userInsert(UserInterface $user): void {
    // Testing for genpass issue.
    $ops = [];
    $ops[] = [[$this, 'batchTask'], [$user], []];

    $batch = [
      'title' => $this->t('Batch test on user insert'),
      'operations' => $ops,
      'init_message' => $this->t('Testing.'),
      'progress_message' => $this->t('Step @current of @total'),
      'error_message' => $this->t('An error occurred during processing.'),
      'finished' => [$this, 'batchFinished'],
    ];

    batch_set($batch);
  }

  /**
   * Batch operation.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param array $context
   *   Context passed by reference through the batch process.
   */
  public static function batchTask(UserInterface $user, &$context): void {
    $context['results']['username'] = $user->getDisplayName();
    $context['message'] = new TranslatableMarkup('Testing...');
    $context['finished'] = 1;
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch was successful.
   * @param array $results
   *   The processed results from the batch.
   * @param array $operations
   *   In case of error, the operations that remained unprocessed.
   */
  public static function batchFinished(
    $success,
    array $results,
    array $operations,
  ) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addStatus(new TranslatableMarkup('Batch test on user insert complete for @user.', [
        '@user' => $results['username'],
      ]));
    }
    else {
      $messenger->addError(new TranslatableMarkup('Error testing batch on user insert.'));
    }
  }

}

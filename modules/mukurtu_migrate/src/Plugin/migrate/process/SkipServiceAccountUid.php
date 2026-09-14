<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Prevents a migrated uid from landing on the submission service account.
 *
 * The mukurtu_submissions module creates a blocked "Submission Forms" account
 * at install time with no explicit uid, so it typically claims a low uid
 * (e.g. 2). If the source site's own uid 2 also gets migrated with a
 * forced destination uid, core's entity migrate destination updates the
 * service account in place instead of creating a new user, merging an
 * unrelated legacy account (and its group memberships) onto it. Skipping
 * just this destination property leaves the uid unset, so Drupal assigns
 * the migrated user a fresh uid instead.
 */
#[MigrateProcess(
  id: 'skip_service_account_uid',
  handle_multiples: FALSE,
)]
class SkipServiceAccountUid extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $service_account_uid = \Drupal::config('mukurtu_submissions.settings')->get('service_account_uid');
    if ($service_account_uid && (int) $value === (int) $service_account_uid) {
      $this->stopPipeline();
      return NULL;
    }
    return $value;
  }

}

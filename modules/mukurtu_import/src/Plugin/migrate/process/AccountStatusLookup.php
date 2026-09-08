<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Resolves an "Active"/"Blocked"/"Pending" cell into status + field_pending.
 *
 * Mirrors the same three-state model the interactive account form already
 * presents (see FormHooks::userStatusPreSaveSubmit()), instead of requiring
 * two separate boolean columns (status, field_pending) whose interaction --
 * field_pending's storage default is 1, and Status always overrides Pending
 * -- isn't obvious from a CSV alone.
 *
 * A blank cell resolves to NULL, which ProtocolAwareUserContent interprets
 * as "use the default (Active) for a new account, or leave an existing
 * account's status untouched on update."
 */
#[MigrateProcess('mukurtu_account_status_lookup')]
class AccountStatusLookup extends ProcessPluginBase {

  /**
   * Maps a normalized status string to its underlying field values.
   */
  protected const STATUS_MAP = [
    'active' => ['status' => 1, 'field_pending' => 0],
    'blocked' => ['status' => 0, 'field_pending' => 0],
    'pending' => ['status' => 0, 'field_pending' => 1],
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    $normalized = mb_strtolower($value);
    if (!isset(self::STATUS_MAP[$normalized])) {
      throw new MigrateException(sprintf('"%s" is not a valid Account Status. Use Active, Blocked, or Pending.', $value));
    }

    return self::STATUS_MAP[$normalized];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Runs a one-row grid migration, keeping its messages for the grid.
 *
 * The grid's migrations have no ID map to store messages in.
 */
class GridRowExecutable extends MigrateExecutable {

  /**
   * Messages saved while importing the row.
   *
   * @var string[]
   */
  public array $messages = [];

  /**
   * {@inheritdoc}
   */
  public function saveMessage($message, $level = MigrationInterface::MESSAGE_ERROR) {
    $this->messages[] = (string) $message;
  }

}

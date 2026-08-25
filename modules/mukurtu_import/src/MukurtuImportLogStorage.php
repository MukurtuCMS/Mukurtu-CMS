<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Reads and writes rows in the mukurtu_import_log table.
 */
class MukurtuImportLogStorage {

  /**
   * The database table name.
   */
  const TABLE = 'mukurtu_import_log';

  public function __construct(protected Connection $database) {
  }

  /**
   * Write a log row for one imported file.
   *
   * @param array $values
   *   Column values keyed by column name. See mukurtu_import_schema().
   *
   * @return int
   *   The id of the inserted row.
   */
  public function log(array $values): int {
    return (int) $this->database->insert(self::TABLE)
      ->fields($values)
      ->execute();
  }

  /**
   * Start a select query against the log table.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   A select query aliased 'l'.
   */
  public function query(): SelectInterface {
    return $this->database->select(self::TABLE, 'l')->fields('l');
  }

  /**
   * Load a single log row.
   *
   * @param int $id
   *   The row id.
   *
   * @return object|null
   *   The row as a stdClass object, or NULL if it doesn't exist.
   */
  public function load(int $id): ?object {
    $row = $this->database->select(self::TABLE, 'l')
      ->fields('l')
      ->condition('l.id', $id)
      ->execute()
      ->fetchObject();
    return $row ?: NULL;
  }

}

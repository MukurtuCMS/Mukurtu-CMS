<?php

declare(strict_types=1);

namespace Drupal\mukurtu_dictionary\DatabaseCompatibility;

use Drupal\Core\Database\DatabaseException;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_db\DatabaseCompatibility\MySql;

/**
 * Custom MySQL database compatibility handler for Mukurtu Dictionary.
 */
class MukurtuDictionaryMySql extends MySql {

  /**
   * {@inheritdoc}
   */
  public function alterNewTable($table, $type = 'text'): void {
    // Use custom collation for the glossary entry field table.
    if (!preg_match('/^search_api_db_mukurtu_dictionary_index_field_glossary_entry/i', $table) || $type !== 'field') {
      parent::alterNewTable($table, $type);
      return;
    }
    $charset = 'utf8mb4';
    $collation = 'utf8mb4_bin';
    try {
      $this->database->query("ALTER TABLE {{$table}} CONVERT TO CHARACTER SET '$charset' COLLATE '$collation'");
    }
    catch (\PDOException | DatabaseException $e) {
      $class = get_class($e);
      $message = $e->getMessage();
      throw new SearchApiException("$class while trying to change collation of $type search data table '$table': $message", 0, $e);
    }
  }

}

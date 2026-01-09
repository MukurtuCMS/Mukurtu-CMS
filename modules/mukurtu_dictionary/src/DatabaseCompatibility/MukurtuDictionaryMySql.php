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
    // We need our glossary_entry facet, which is based on the
    // field_glossary_entry field to be accent sensitive. This is controlled at
    // the database level by the collation setting on the table. By default,
    // the collation setting is set to utf8_general_ci. utf8_general_ci is case
    // insensitive and accent insensitive, eg. "é" == "e", which is not what we
    // want for our glossary_entry facet. Therefore, we need to change the
    // collation settings to utf8mb4_bin on the main mukurtu_dictionary_index
    // table as well as the field_glossary_entry table.
    if (!preg_match('/^search_api_db_mukurtu_dictionary_index(?:$|_field_glossary_entry)/i', $table)) {
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

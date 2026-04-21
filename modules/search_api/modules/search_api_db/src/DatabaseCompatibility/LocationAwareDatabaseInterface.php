<?php

namespace Drupal\search_api_db\DatabaseCompatibility;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Represents a database that can handle location filters.
 */
interface LocationAwareDatabaseInterface extends DatabaseCompatibilityHandlerInterface {

  /**
   * Retrieves whether or not this database supports location filters.
   *
   * Some databases, like PostgreSQL and SQLite, need extensions to support
   * spatial search, whereas it is permanently available in MySQL 5.7+.
   */
  public function isLocationEnabled(): bool;

  /**
   * Returns the schema definition to use for location field database columns.
   *
   * @return array
   *   Column configuration to use for a location field's database column.
   */
  public function getLocationFieldSqlType(): array;

  /**
   * Converts a location field value to the format required by this database.
   *
   * @param mixed $value
   *   The value to convert.
   * @param string $original_type
   *   The value's original type.
   *
   * @return mixed
   *   The converted value.
   */
  public function convertValue($value, string $original_type);

  /**
   * Adds location clauses based on the "search_api_location" query option.
   *
   * This will be the first method called when the option is present and should
   * add any expressions (or similar) to the DB query which are also required by
   * location-based sorts or conditions.
   *
   * @param string $table_alias
   *   Database table alias for the base table.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search api query.
   * @param \Drupal\Core\Database\Query\SelectInterface $db_query
   *   The search query.
   */
  public function addLocationFilter(string $table_alias, QueryInterface $query, SelectInterface $db_query): void;

  /**
   * Adds distance-based sorting clauses.
   *
   * @param string $field_name
   *   The name of the location field.
   * @param string $order
   *   The order to sort in, "ASC" or "DESC".
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param \Drupal\Core\Database\Query\SelectInterface $db_query
   *   The database query.
   *
   * @return bool
   *   TRUE if the sort could be added, FALSE otherwise.
   */
  public function addLocationSort(string $field_name, string $order, QueryInterface $query, SelectInterface $db_query): bool;

  /**
   * Adds location-based conditions to a database query.
   *
   * @param string $field_name
   *   The name of the location field.
   * @param $value
   *   The filter value.
   * @param string $operator
   *   The filter operator.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param \Drupal\Core\Database\Query\SelectInterface $db_query
   *   The database query.
   */
  public function addLocationDbCondition(string $field_name, $value, string $operator, QueryInterface $query, SelectInterface $db_query): void;

}

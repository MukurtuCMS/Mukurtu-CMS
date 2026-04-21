<?php

namespace Drupal\search_api_db\DatabaseCompatibility;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;

/**
 * Represents a MySQL-based database.
 */
class MySql extends GenericDatabase implements LocationAwareDatabaseInterface {

  /**
   * {@inheritdoc}
   */
  public function alterNewTable($table, $type = 'text') {
    // The Drupal MySQL integration defaults to using a 4-byte-per-character
    // encoding, which would make it impossible to use our normal 255 characters
    // long varchar fields in a primary key (since that would exceed the key's
    // maximum size). Therefore, we have to convert all tables to the "utf8"
    // character set – but we only want to make fulltext tables case-sensitive.
    $charset = $type === 'text' ? 'utf8mb4' : 'utf8';
    $collation = $type === 'text' ? 'utf8mb4_bin' : 'utf8_general_ci';
    try {
      $this->database->query("ALTER TABLE {{$table}} CONVERT TO CHARACTER SET '$charset' COLLATE '$collation'");
      // Even for text tables, we need the "item_id" column to have the same
      // collation as everywhere else. Otherwise, this can slow down search
      // queries significantly.
      if ($type === 'text') {
        $this->database->query("ALTER TABLE {{$table}} MODIFY [item_id] VARCHAR(150) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
      }
    }
    catch (\PDOException | DatabaseException $e) {
      $class = get_class($e);
      $message = $e->getMessage();
      throw new SearchApiException("$class while trying to change collation of $type search data table '$table': $message", 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexValue($value, $type = 'text') {
    $value = parent::preprocessIndexValue($value, $type);
    // As MySQL removes trailing whitespace when computing primary keys, we need
    // to do the same or pseudo-duplicates could cause an exception ("Integrity
    // constraint violation: Duplicate entry") during indexing.
    return rtrim($value);
  }

  /**
   * {@inheritdoc}
   */
  public function orderByRandom(SelectInterface $query) {
    $seed = $query->getMetaData('search_api_random_sort_seed');
    if (!isset($seed) || !is_numeric($seed)) {
      $seed = '';
    }
    $alias = $query->addExpression("rand($seed)", 'random_order_field');
    $query->orderBy($alias);
  }

  /**
   * {@inheritdoc}
   */
  public function isLocationEnabled(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationFieldSqlType(): array {
    return [
      'type'   => 'varchar',
      'length' => 255,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function convertValue($value, string $original_type) {
    if (!$value || !is_string($value) || !str_contains($value, ',')) {
      return NULL;
    }
    [$lat, $lon] = explode(',', $value);
    return 'POINT(' . ((float) $lon) . ' ' . ((float) $lat) . ')';
  }

  /**
   * Adds location clauses based on the "search_api_location" query option.
   *
   * Relies on the ST_Distance_Sphere() MySQL function for distance
   * calculation.
   *
   * The expression *alias* for ST_Distance_Sphere() is stored as part of the
   * search_api_location query option under the *expression_alias* key for
   * later use, such as in a distance-based sort handler.
   *
   * @param string $table_alias
   *   Database table alias for the base table.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search api query.
   * @param \Drupal\Core\Database\Query\SelectInterface $db_query
   *   The search query.
   *
   * @see https://dev.mysql.com/doc/refman/5.7/en/spatial-convenience-functions.html#function_st-distance-sphere
   * @see https://dev.mysql.com/doc/refman/5.7/en/spatial-types.html
   */
  public function addLocationFilter(string $table_alias, QueryInterface $query, SelectInterface $db_query): void {
    $location_options = (array) $query->getOption('search_api_location', []);
    foreach ($location_options as &$condition) {
      $location_field_name = "$table_alias.{$condition['field']}";
      $distance_field_alias = "{$condition['field']}__distance";

      // MySQL's spatial functions use meter as distance unit whereas Solr uses
      // kilometer by default. To maintain compatibility with Solr, here we
      // divide the resultant distance by a thousand so that it is in kilometer.
      $distance_field_alias = $db_query->addExpression("ST_Distance_Sphere(Point(:centre_lon, :centre_lat), ST_PointFromText($location_field_name)) / 1000", $distance_field_alias, [
        ':centre_lat' => $condition['lat'],
        ':centre_lon' => $condition['lon'],
      ]);

      if (array_key_exists('radius', $condition)) {
        // At the moment, the search_api_location module does not tell us what
        // Views filter operator is in use.  So we default to "less than".
        // @see https://www.drupal.org/project/search_api_location/issues/2913680
        $query->addCondition($condition['field'], $condition['radius'], '<');
      }

      // The alias used by the expression is later used in sorting.
      $condition['expression_alias'] = $distance_field_alias;

      // When GROUP BY is in use, query breaks unless our expression is grouped.
      if ($db_query->getGroupBy()) {
        $db_query->groupBy($location_field_name);
      }
    }

    if ($location_options) {
      $query->setOption('search_api_location', $location_options);
    }
  }

  /**
   * Applies coordinate-based location sort.
   *
   * Known to work for the search_api_location_distance Views sort plugin.
   * Assumes that a search_api_location Views filter is in use for
   * distance-based search.
   *
   * The "expression_alias" key refers to the ST_Distance_Sphere() SQL
   * expression's alias added in self::addLocationFilter().
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
   *
   * @see self::addLocationFilter()
   */
  public function addLocationSort(string $field_name, string $order, QueryInterface $query, SelectInterface $db_query): bool {
    $location_options = (array) $query->getOption('search_api_location', []);
    foreach ($location_options as $location_search_condition) {
      $distance_pseudo_field_name = $location_search_condition['field'] . '__distance';
      if ($distance_pseudo_field_name === $field_name
          && isset($location_search_condition['expression_alias'])) {
        $db_query->orderBy($location_search_condition['expression_alias'], $order);
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Adds location-based conditions to a database query.
   *
   * The "expression_alias" key refers to the ST_Distance_Sphere() SQL
   * expression's alias added in self::addLocationFilter().
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
   *
   * @see self::addLocationFilter()
   */
  public function addLocationDbCondition(string $field_name, $value, string $operator, QueryInterface $query, SelectInterface $db_query): void {
    $location_options = (array) $query->getOption('search_api_location', []);
    foreach ($location_options as $location_search_condition) {
      if ($field_name === $location_search_condition['field']
          && isset($location_search_condition['expression_alias'])) {
        $db_query->havingCondition($location_search_condition['expression_alias'], $value, $operator);
        return;
      }
    }
  }

}

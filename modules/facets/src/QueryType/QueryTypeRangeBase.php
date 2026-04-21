<?php

namespace Drupal\facets\QueryType;

use Drupal\facets\Result\Result;

/**
 * A base class for query type plugins adding range.
 */
abstract class QueryTypeRangeBase extends QueryTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;
    $options = &$query->getOptions();

    $operator = $this->facet->getQueryOperator();
    $field_identifier = $this->facet->getFieldIdentifier();
    $exclude = $this->facet->getExclude();
    $options['search_api_facets'][$field_identifier] = $this->getFacetOptions();

    // Add the filter to the query if there are active values.
    $active_items = $this->facet->getActiveItems();
    $filter = $query->createConditionGroup($operator, ['facet:' . $field_identifier]);
    if (count($active_items)) {
      foreach ($active_items as $value) {
        $range = $this->calculateRange($value) + [
          'include_lower' => TRUE,
          'include_upper' => TRUE,
        ];

        $conjunction = $exclude ? 'OR' : 'AND';
        $item_filter = $query->createConditionGroup($conjunction, ['facet:' . $field_identifier]);
        $item_filter->addCondition($this->facet->getFieldIdentifier(), $range['start'], $exclude ? '<' : ($range['include_lower'] ? '>=' : '>'));
        $item_filter->addCondition($this->facet->getFieldIdentifier(), $range['stop'], $exclude ? '>' : ($range['include_upper'] ? '<=' : '<'));

        $filter->addConditionGroup($item_filter);
      }
      $query->addConditionGroup($filter);
    }
  }

  /**
   * Calculate the range for a given facet filter value.
   *
   * Used when adding active items in self::execute() to $this->query to include
   * the range conditions for the value.
   *
   * @param string $value
   *   The raw value for the facet filter.
   *
   * @return array
   *   Keyed with 'start' and 'stop' values.
   */
  abstract public function calculateRange($value);

  /**
   * {@inheritdoc}
   */
  public function build() {
    // If there were no results or no query object, we can't do anything.
    if (empty($this->results)) {
      return $this->facet;
    }

    $query_operator = $this->facet->getQueryOperator();
    $facet_results = [];
    $unprocessed_active_items = $this->facet->getActiveItems();
    foreach ($this->results as $result) {
      // Go through the results and add facet results grouped by filters
      // defined by self::calculateResultFilter().
      if ($result['count'] || $query_operator === 'or') {
        $count = $result['count'];
        if ($result_filter = $this->calculateResultFilter(trim($result['filter'], '"'))) {
          if ($result_filter === 'NULL' || $result_filter === '') {
            // "Missing" facet items could not be handled in ranges.
            continue;
          }

          if (isset($facet_results[$result_filter['raw']])) {
            $facet_results[$result_filter['raw']]->setCount(
              $facet_results[$result_filter['raw']]->getCount() + $count
            );
          }
          else {
            $facet_results[$result_filter['raw']] = new Result($this->facet, $result_filter['raw'], $result_filter['display'], $count);
          }
          if (($key = array_search($result_filter['raw'], $unprocessed_active_items)) !== FALSE) {
            unset($unprocessed_active_items[$key]);
          }
        }
      }
    }

    if (!$this->getHierarchy()) {
      // Add unprocessed active values to the result. These are selected items
      // that do not match the results anymore.
      foreach ($unprocessed_active_items as $val) {
        $result = new Result($this->facet, $val, $this->getDisplayValue($val), 0);
        $result->setActiveState(TRUE);
        $facet_results[] = $result;
      }
    }

    $this->facet->setResults($facet_results);
    return $this->facet;
  }

  /**
   * Returns the display value for the given raw value.
   *
   * @param mixed $raw_value
   *   The raw value.
   *
   * @return mixed
   *   The display value.
   */
  public function getDisplayValue($raw_value) {
    return $raw_value;
  }

  /**
   * Implement this method to return TRUE if the facet has a hierarchy.
   */
  protected function getHierarchy() {
    return FALSE;
  }

  /**
   * Calculate the grouped facet filter for a given value.
   *
   * @param string $value
   *   The raw value for the facet before grouping.
   *
   * @return array
   *   Keyed by 'display' value to be shown to the user, and 'raw' to be used
   *   for the url.
   */
  abstract public function calculateResultFilter($value);

}

<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\NumericFilter;

/**
 * Defines a filter for filtering on numeric values.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('search_api_numeric')]
class SearchApiNumeric extends NumericFilter {

  use SearchApiFilterTrait;

  /**
   * Defines the operators supported by this filter.
   *
   * @return array[]
   *   An associative array of operators, keyed by operator ID, with information
   *   about that operator:
   *   - title: The full title of the operator (translated).
   *   - short: The short title of the operator (translated).
   *   - method: The method to call for this operator in query().
   *   - values: The number of values that this operator expects/needs.
   */
  public function operators() {
    $operators = parent::operators();
    unset($operators['regular_expression']);
    return $operators;
  }

}

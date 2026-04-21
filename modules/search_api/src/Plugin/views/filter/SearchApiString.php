<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;

/**
 * Defines a filter for adding conditions on string fields to the query.
 *
 * Due to the way the Search API works, this inherits from the numeric handler,
 * since the operators most closely resemble those from Views' own numeric
 * filter. (The Search API doesn't have operators for "contains", "starts
 * with", etc. as used by Views' string filter.)
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('search_api_string')]
class SearchApiString extends SearchApiNumeric {

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    // The parent implementation in NumericFilter uses is_numeric() checks now,
    // so we need to override it to check for any values.
    if ($this->value['min'] != '' && $this->value['max'] != '') {
      $operator = $this->operator == 'between' ? 'BETWEEN' : 'NOT BETWEEN';
      $this->getQuery()->addWhere($this->options['group'], $field, [
        $this->value['min'],
        $this->value['max'],
      ], $operator);
    }
    elseif ($this->value['min'] != '') {
      $operator = $this->operator == 'between' ? '>=' : '<';
      $this->getQuery()->addWhere($this->options['group'], $field, $this->value['min'], $operator);
    }
    elseif ($this->value['max'] != '') {
      $operator = $this->operator == 'between' ? '<=' : '>';
      $this->getQuery()->addWhere($this->options['group'], $field, $this->value['max'], $operator);
    }
  }

}

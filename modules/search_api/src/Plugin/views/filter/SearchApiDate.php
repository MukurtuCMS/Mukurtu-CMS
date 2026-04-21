<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Defines a filter for filtering on dates.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('search_api_date')]
class SearchApiDate extends Date {

  use SearchApiFilterTrait;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|null
   */
  protected $timeService;

  /**
   * Retrieves the time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  public function getTimeService() {
    return $this->timeService ?: \Drupal::time();
  }

  /**
   * Sets the time service.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The new time service.
   *
   * @return $this
   */
  public function setTimeService(TimeInterface $time_service) {
    $this->timeService = $time_service;
    return $this;
  }

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

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $return = parent::acceptExposedInput($input);

    if (!$return) {
      // The parent class doesn't always handle operators with 0 or 2 values
      // correctly.
      $operators = $this->operators();
      $num_values = $operators[$this->operator]['values'];
      if ($num_values == 0) {
        return TRUE;
      }
      // @todo Remove this once we depend on a Core version that fixed #3202489.
      elseif ($num_values == 2
          && (!empty($this->value['min']) || !empty($this->value['max']))) {
        return TRUE;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    if (!empty($this->value['max'])
        && ($this->value['type'] ?? '') != 'offset'
        && !str_contains($this->value['max'], ':')) {
      // No time was specified, so make the date range inclusive.
      $this->value['max'] .= ' +1 day';
    }
    $base_timestamp = ($this->value['type'] ?? '') == 'offset'
      ? $this->getTimeService()->getRequestTime()
      : 0;
    $a = $b = NULL;
    if (!empty($this->value['min'])) {
      $a = strtotime($this->value['min'], $base_timestamp);
    }
    if (!empty($this->value['max'])) {
      $b = strtotime($this->value['max'], $base_timestamp);
    }

    $real_field = $this->realField;
    $group = $this->options['group'];

    if (isset($a, $b)) {
      $operator = strtoupper($this->operator);
      $this->getQuery()->addCondition($real_field, [$a, $b], $operator, $group);
    }
    elseif (isset($a)) {
      // Using >= for BETWEEN and < for NOT BETWEEN.
      $operator = $this->operator === 'between' ? '>=' : '<';
      $this->getQuery()->addCondition($real_field, $a, $operator, $group);
    }
    elseif (isset($b)) {
      // Using <= for BETWEEN and > for NOT BETWEEN.
      $operator = $this->operator === 'between' ? '<=' : '>';
      $this->getQuery()->addCondition($real_field, $b, $operator, $group);
    }
  }

  /**
   * Filters by a simple operator (=, !=, >, etc.).
   *
   * @param string $field
   *   The views field.
   */
  protected function opSimple($field) {
    if (!isset($this->value['value']) || $this->value['value'] === '') {
      return;
    }
    $value = intval(strtotime($this->value['value'], 0));
    if (($this->value['type'] ?? '') == 'offset') {
      $time = $this->getTimeService()->getRequestTime();
      $value = strtotime($this->value['value'], $time);
    }

    $this->getQuery()->addCondition($this->realField, $value, $this->operator, $this->options['group']);
  }

}

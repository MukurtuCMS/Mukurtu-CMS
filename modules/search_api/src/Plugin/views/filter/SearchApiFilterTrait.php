<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\SearchApiHandlerTrait;

/**
 * Provides a trait to use for Search API Views filters.
 */
trait SearchApiFilterTrait {

  use SearchApiHandlerTrait;

  /**
   * Adds a form for entering the value or values for the filter.
   *
   * Overridden to remove fields that won't be used (but aren't hidden either
   * because of a small bug/glitch in the original form code – see #2637674).
   *
   * @param array $form
   *   The form array, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\views\Plugin\views\filter\FilterPluginBase::valueForm()
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (isset($form['value']['min']) && !$this->operatorValues(2)) {
      unset($form['value']['min'], $form['value']['max']);
    }
  }

  /**
   * Filters by a simple operator (=, !=, >, etc.).
   *
   * @param string $field
   *   The views field.
   */
  protected function opSimple($field = NULL) {
    // Since some of the filters using this trait have a nested "value" key and
    // some don't, we need to take both variants into account.
    if (($this->value['value'] ?? $this->value ?? '') === '') {
      return;
    }
    parent::opSimple($field);
  }

  /**
   * Adds a filter to the search query.
   *
   * Overridden to avoid errors because of SQL-specific functionality being used
   * when "Many To One" is used as a base class.
   *
   * @see \Drupal\views\Plugin\views\filter\ManyToOne::opHelper()
   */
  protected function opHelper() {
    if (empty($this->value)) {
      return;
    }

    // Form API returns unchecked options in the form of option_id => 0. This
    // breaks the generated query for "is all of" filters so we remove them.
    $this->value = array_filter($this->value, [static::class, 'arrayFilterZero']);

    // Potentially, the value is now empty.
    if (empty($this->value)) {
      return;
    }

    if ($this->operator !== 'and') {
      $operator = $this->operator === 'not' ? 'NOT IN' : 'IN';
      $this->getQuery()->addCondition($this->realField, $this->value, $operator, $this->options['group']);
      return;
    }

    $condition_group = $this->getQuery()->createConditionGroup();
    $this->getQuery()->addConditionGroup($condition_group, $this->options['group']);
    foreach ($this->value as $value) {
      $condition_group->addCondition($this->realField, $value, '=');
    }
  }

}

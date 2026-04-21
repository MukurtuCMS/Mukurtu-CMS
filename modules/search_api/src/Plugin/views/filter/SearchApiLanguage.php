<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\LanguageFilter;

/**
 * Defines a filter for filtering on the language of items.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('search_api_language')]
class SearchApiLanguage extends LanguageFilter {

  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $substitutions = static::queryLanguageSubstitutions();
    foreach ($this->value as $i => $value) {
      if (isset($substitutions[$value])) {
        $this->value[$i] = $substitutions[$value];
      }
    }

    // Only set the languages using $query->setLanguages() if the condition
    // would be placed directly on the query, as an AND condition.
    $query = $this->getQuery();
    $op_in_required = $this->operator == 'in'
      && $query->getGroupType($this->options['group']) === 'AND'
      && $query->getGroupOperator() === 'AND';
    if ($this->realField === 'search_api_language' && $op_in_required) {
      $query->setLanguages($this->value);
    }
    else {
      parent::query();
    }

    // Also add a query option for any direct language filter including all
    // included languages, in case any component needs this information in
    // addition to the filter we placed.
    if ($op_in_required && $this->value) {
      $query->setOption('search_api_included_languages', array_values($this->value));
    }
  }

}

<?php

namespace Drupal\search_api\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Provides a sort plugin for Search API views.
 */
#[ViewsSort('search_api')]
class SearchApiSort extends SortPluginBase {

  /**
   * The associated views query object.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // When there are exposed sorts, the "exposed form" plugin will set
    // $query->orderby to an empty array. Therefore, if that property is set,
    // we here remove all previous sorts.
    if (isset($this->query->orderby)) {
      $this->query->orderby = NULL;
      $sort = &$this->query->getSort();
      $sort = [];
    }
    $this->query->sort($this->realField, $this->options['order']);
  }

}

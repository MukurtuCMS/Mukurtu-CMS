<?php

namespace Drupal\geolocation\Plugin\views\sort;

use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sort handler for geolocation field.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("geolocation_sort_proximity")
 */
class ProximitySort extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!($this->query instanceof Sql)) {
      return;
    }

    $field = $this->displayHandler->getHandler('field', $this->field);

    if (!empty($field->field_alias) && $field->field_alias != 'unknown') {
      $this->query->addOrderBy(NULL, NULL, $this->options['order'], $field->field_alias);
      $this->tableAlias = $field->tableAlias;
    }
  }

}

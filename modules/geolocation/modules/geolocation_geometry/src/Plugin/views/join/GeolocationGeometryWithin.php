<?php

namespace Drupal\geolocation_geometry\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginInterface;

/**
 * Geometry joins.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("geolocation_geometry_within")
 */
class GeolocationGeometryWithin extends JoinPluginBase implements JoinPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    /** @var \Drupal\Core\Database\Query\Select $select_query */

    $geometry_field = ($table['alias'] ?: $this->table) . '.' . $this->field . '_geometry';
    $within_field = $this->leftTable . '.' . $this->leftField . '_geometry';
    $condition = 'ST_Within(' . $within_field . ', ' . $geometry_field . ')';

    $select_query->addJoin($this->type, $this->table, $table['alias'], $condition);
  }

}

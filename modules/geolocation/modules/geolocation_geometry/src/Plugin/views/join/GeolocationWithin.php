<?php

namespace Drupal\geolocation_geometry\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginInterface;

/**
 * Geometry joins.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("geolocation_within")
 */
class GeolocationWithin extends JoinPluginBase implements JoinPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    /** @var \Drupal\Core\Database\Query\Select $select_query */

    $geometry_field = $table['alias'] . '.' . $this->field . '_geometry';
    $latitude_field = $this->leftTable . '.' . $this->leftField . '_lat';
    $longitude_field = $this->leftTable . '.' . $this->leftField . '_lng';

    $condition = "ST_Within(ST_PointFromText(CONCAT('POINT(', " . $longitude_field . ", ' ', " . $latitude_field . ", ')'), 4326), " . $geometry_field . ")";

    $select_query->addJoin($this->type, $this->table, $table['alias'], $condition);
  }

}

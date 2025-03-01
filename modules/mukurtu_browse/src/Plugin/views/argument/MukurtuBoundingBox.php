<?php

namespace Drupal\mukurtu_browse\Plugin\views\argument;

use Drupal\search_api\Plugin\views\argument\SearchApiStandard;

/**
 * Defines a filter for a geographic bounding box.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("mukurtu_bounding_box")
 */
class MukurtuBoundingBox extends SearchApiStandard {

  /**
   * Parse the query argument into a bounding box.
   */
  protected function parseBoundingBox() {
    $bbox = [];
    $coordinates = explode(',', $this->argument);

    if (count($coordinates) == 4) {
      $bbox['left'] = (float) $coordinates[0];
      $bbox['bottom'] = (float) $coordinates[1];
      $bbox['right'] = (float) $coordinates[2];
      $bbox['top'] = (float) $coordinates[3];
    }

    return $bbox;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    // Parse argument into bottom/top/left/right.
    $bbox = $this->parseBoundingBox();

    // Alter the query to restrict to our bounding box.
    if (!empty($bbox)) {
      $this->query->addCondition('centroid_lat', $bbox['bottom'], '>=');
      $this->query->addCondition('centroid_lat', $bbox['top'], '<=');
      $this->query->addCondition('centroid_lon', $bbox['left'], '>=');
      $this->query->addCondition('centroid_lon', $bbox['right'], '<=');
    }
  }

}

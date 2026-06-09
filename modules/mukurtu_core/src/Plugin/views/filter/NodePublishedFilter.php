<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filters nodes by published status with human-readable labels.
 *
 * @ViewsFilter("mukurtu_node_published_filter")
 */
class NodePublishedFilter extends BooleanOperator {

  public function getValueOptions(): array {
    $this->valueOptions = [
      1 => $this->t('Published'),
      0 => $this->t('Unpublished'),
    ];
    return $this->valueOptions;
  }

}

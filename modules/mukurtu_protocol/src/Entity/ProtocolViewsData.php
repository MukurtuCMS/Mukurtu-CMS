<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Protocol entities.
 */
class ProtocolViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}

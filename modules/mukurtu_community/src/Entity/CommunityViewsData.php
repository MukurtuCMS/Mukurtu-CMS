<?php

namespace Drupal\mukurtu_community\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Community entities.
 */
class CommunityViewsData extends EntityViewsData {

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

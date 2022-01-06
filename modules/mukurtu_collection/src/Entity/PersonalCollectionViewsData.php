<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Personal collection entities.
 */
class PersonalCollectionViewsData extends EntityViewsData {

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

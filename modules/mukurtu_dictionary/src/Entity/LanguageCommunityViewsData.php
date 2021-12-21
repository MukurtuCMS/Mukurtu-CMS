<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Language community entities.
 */
class LanguageCommunityViewsData extends EntityViewsData {

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

<?php

namespace Drupal\term_merge_manager\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Term merge from entities.
 */
class TermMergeFromViewsData extends EntityViewsData {

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

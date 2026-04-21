<?php

namespace Drupal\dashboards;

use Drupal\dashboards\Plugin\SectionStorage\DashboardSectionStorage;
use Drupal\dashboards\Plugin\SectionStorage\UserDashboardSectionStorage;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Simple helper trait.
 */
trait LayoutBuilderRestrictionHelperTrait {

  /**
   * Check if storage is a dashboard storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section
   *   Section storage.
   *
   * @return bool
   *   Is is a dashboard storage.
   */
  public function isDashboardStorage(SectionStorageInterface $section): bool {
    if ($section instanceof DashboardSectionStorage
       || $section instanceof UserDashboardSectionStorage) {
      return TRUE;
    }
    return FALSE;
  }

}

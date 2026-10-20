<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Traits;

/**
 * Installs the real visitors table and writes rows to it, for Kernel tests.
 */
trait VisitorsTableTestTrait {

  /**
   * Installs visitors and visitors_geoip for real.
   *
   * Not via $modules: visitors_geoip adds the location_region/city/latitude/
   * longitude columns in hook_install(), which $modules does not run.
   */
  protected function installVisitors(): void {
    \Drupal::service('module_installer')->install(['visitors', 'visitors_geoip']);
  }

  /**
   * Inserts one visit, filling every required column the test leaves out.
   *
   * @param array $fields
   *   Column values for the visit.
   *
   * @return int
   *   The new visitors_id.
   */
  protected function insertVisit(array $fields): int {
    \Drupal::moduleHandler()->loadInclude('visitors', 'install');
    $defaults = [];
    // Read from contrib's own schema so this keeps inserting a valid row if
    // visitors adds another required column.
    foreach (visitors_schema()['visitors']['fields'] as $column => $spec) {
      if (!empty($spec['not null']) && !array_key_exists('default', $spec) && $spec['type'] !== 'serial') {
        $defaults[$column] = in_array($spec['type'], ['int', 'float', 'numeric'], TRUE) ? 0 : '';
      }
    }
    // Nullable, but the tracker always writes 0 or 1, and the reports filter
    // on bot <> 1, which a NULL never satisfies.
    $defaults['bot'] = 0;
    $defaults['visitors_date_time'] = \Drupal::time()->getRequestTime();

    return (int) \Drupal::database()->insert('visitors')
      ->fields($fields + $defaults)
      ->execute();
  }

}

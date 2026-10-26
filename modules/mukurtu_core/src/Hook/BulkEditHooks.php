<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_core\BulkEdit\MukurtuModifyEntityValues;

/**
 * Adjusts Views Bulk Edit for Mukurtu.
 */
class BulkEditHooks {

  /**
   * Implements hook_action_info_alter().
   *
   * Swaps in a "Modify field values" action that can add a new term to more
   * than one batch of items. Only alters VBE's own class, so a site that
   * replaced it keeps its replacement.
   */
  #[Hook('action_info_alter')]
  public function actionInfoAlter(array &$definitions): void {
    if (($definitions['views_bulk_edit']['class'] ?? NULL) === 'Drupal\views_bulk_edit\Plugin\Action\ModifyEntityValues') {
      $definitions['views_bulk_edit']['class'] = MukurtuModifyEntityValues::class;
    }
  }

}

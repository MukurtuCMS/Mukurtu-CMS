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

  /**
   * Implements hook_form_FORM_ID_alter() for the bulk edit page.
   *
   * Keeps VBO's "Selected N items" list from growing the sticky action bar
   * over the whole table: the list scrolls within a capped height. VBO
   * replaces the list's contents as the selection changes but keeps this
   * wrapper, so the wrapper carries the scroll region's attributes. It's
   * focusable so keyboard users can scroll it, and named by the summary.
   */
  #[Hook('form_views_form_mukurtu_bulk_edit_content_page_alter')]
  public function bulkEditFormAlter(array &$form): void {
    if (!isset($form['header']['views_bulk_operations_bulk_form']['multipage']['list'])) {
      return;
    }
    $selection = &$form['header']['views_bulk_operations_bulk_form']['multipage'];
    $selection['#summary_attributes']['id'] = 'mukurtu-bulk-edit-selection-summary';
    $selection['list']['#wrapper_attributes']['class'][] = 'mukurtu-bulk-edit-selection';
    $selection['list']['#wrapper_attributes'] += [
      'role' => 'region',
      'tabindex' => '0',
      'aria-labelledby' => 'mukurtu-bulk-edit-selection-summary',
    ];
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_columns';
    // The action bar's own count covers only the current page.
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_selection_count';
  }

}

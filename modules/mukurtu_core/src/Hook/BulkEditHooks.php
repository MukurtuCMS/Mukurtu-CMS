<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\BulkEdit\MukurtuModifyEntityValues;
use Drupal\views\ViewExecutable;

/**
 * Adjusts Views Bulk Edit for Mukurtu.
 */
class BulkEditHooks {

  use StringTranslationTrait;

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
   */
  #[Hook('form_views_form_mukurtu_bulk_edit_content_page_alter')]
  public function bulkEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->disableViewOnlyRows($form, $form_state);
    $this->capSelectionList($form);
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_columns';
    // The action bar's own count can disagree with what's actually selected.
    $form['#attached']['library'][] = 'mukurtu_core/bulk_edit_selection_count';
  }

  /**
   * Swaps the row checkbox for a disabled one on items the editor can't edit.
   *
   * The page lists what the editor can view, which can include items they
   * can't edit, such as ones under an open protocol they have no role in.
   * Edit access is worked out per item rather than in the query, so these
   * rows stay listed but can't be selected. The replacement has no VBO class
   * and the browser's default value, "on", which VBO's script ignores, so
   * selecting all rows on the page leaves these out too.
   */
  protected function disableViewOnlyRows(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->getBuildInfo()['args'][0] ?? NULL;
    if (!$view instanceof ViewExecutable || !isset($form['views_bulk_operations_bulk_form'])) {
      return;
    }
    foreach (Element::children($form['views_bulk_operations_bulk_form']) as $index) {
      $entity = $view->result[$index]->_entity ?? NULL;
      if (!$entity || $entity->access('update')) {
        continue;
      }
      $checkbox = &$form['views_bulk_operations_bulk_form'][$index];
      $checkbox['#disabled'] = TRUE;
      $checkbox['#default_value'] = FALSE;
      $checkbox['#return_value'] = 'on';
      $checkbox['#attributes']['class'] = array_diff($checkbox['#attributes']['class'] ?? [], ['js-vbo-checkbox']);
      $checkbox['#description'] = $this->t("You can't edit this item.");
      $checkbox['#description_display'] = 'invisible';
    }
  }

  /**
   * Keeps VBO's "Selected N items" list from growing the sticky action bar.
   *
   * The list scrolls within a capped height instead of covering the table.
   * VBO replaces the list's contents as the selection changes but keeps this
   * wrapper, so the wrapper carries the scroll region's attributes. It's
   * focusable so keyboard users can scroll it, and named by the summary.
   */
  protected function capSelectionList(array &$form): void {
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
  }

}

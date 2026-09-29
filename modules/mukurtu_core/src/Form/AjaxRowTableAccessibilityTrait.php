<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shared plumbing for accessible AJAX-repeatable-row tables and fieldsets.
 *
 * An #ajax partial replace destroys and recreates its wrapper element, so
 * the wrapper itself can never carry a persistent aria-live region, and
 * focus dropped by a removed button is never restored automatically. This
 * trait builds a live region as a sibling of the #ajax wrapper (so it
 * survives the replace) and marks the element returned from an #ajax
 * callback so mukurtu_core/ajax-row-table-status can announce the change
 * and restore focus.
 */
trait AjaxRowTableAccessibilityTrait {

  /**
   * Builds a visually-hidden live region for announcing table changes.
   *
   * Must be added to the form as a sibling of the #ajax wrapper element,
   * never nested inside it - a live region destroyed and recreated by the
   * same ReplaceCommand it's supposed to announce never fires.
   *
   * @param string $html_id
   *   HTML id for the live region element.
   *
   * @return array
   *   A render array for the live region.
   */
  protected function buildAjaxRowTableStatusRegion(string $html_id): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => $html_id,
        'class' => ['visually-hidden'],
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
      ],
    ];
  }

  /**
   * Marks an #ajax callback's returned element for the shared status behavior.
   *
   * The marker goes on the element being returned (a descendant of the
   * #ajax wrapper), never the wrapper itself: Drupal.attachBehaviors() runs
   * with the wrapper as `context` after the swap, and
   * context.querySelectorAll() only matches descendants of context, never
   * context itself.
   *
   * @param array $element
   *   The render array being returned from the #ajax callback, by
   *   reference.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $message
   *   The already-translated, already-pluralized status message to
   *   announce.
   * @param string $status_region_id
   *   HTML id of the live region built by buildAjaxRowTableStatusRegion().
   * @param string|null $focus_id
   *   HTML id of the stable control to focus after the swap, if any.
   */
  protected function markAjaxRowTableChanged(array &$element, TranslatableMarkup|string $message, string $status_region_id, ?string $focus_id = NULL): void {
    $element['#attributes']['data-mukurtu-ajax-row-table-changed'] = 'true';
    $element['#attributes']['data-mukurtu-status-message'] = $message;
    $element['#attributes']['data-mukurtu-status-target'] = $status_region_id;
    if ($focus_id !== NULL) {
      $element['#attributes']['data-mukurtu-focus-target'] = $focus_id;
    }
  }

  /**
   * Attaches the shared JS behavior that reads the marker data attributes.
   *
   * @param array $form
   *   The form render array, by reference.
   */
  protected function attachAjaxRowTableStatusLibrary(array &$form): void {
    $form['#attached']['library'][] = 'mukurtu_core/ajax-row-table-status';
  }

}

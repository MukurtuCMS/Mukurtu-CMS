/**
 * @file
 * Shared behavior for AJAX-repeatable-row tables/fieldsets across Mukurtu
 * admin forms (import mapping, collection organization, content warnings).
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Announces AJAX row-table changes and restores focus after the swap.
   *
   * An #ajax callback destroys and recreates its wrapper element, silently
   * dropping focus to <body> when the clicked Remove button is destroyed.
   * AjaxRowTableAccessibilityTrait::markAjaxRowTableChanged() marks the
   * element it returns (never the wrapper itself) with
   * [data-mukurtu-ajax-row-table-changed] plus the status/focus data
   * attributes read below.
   *
   * The marked element is always a descendant of the #ajax wrapper, not the
   * wrapper itself, because Drupal.attachBehaviors() runs with the wrapper
   * as `context` after the swap, and context.querySelectorAll() only
   * matches descendants of context, never context itself.
   */
  Drupal.behaviors.mukurtuAjaxRowTableStatus = {
    attach: function (context) {
      once(
        'mukurtu-ajax-row-table-status',
        '[data-mukurtu-ajax-row-table-changed]',
        context
      ).forEach(function (el) {
        el.removeAttribute('data-mukurtu-ajax-row-table-changed');

        const statusRegion = document.getElementById(el.dataset.mukurtuStatusTarget || '');
        if (statusRegion) {
          statusRegion.textContent = el.dataset.mukurtuStatusMessage || '';
        }

        const focusTarget = document.getElementById(el.dataset.mukurtuFocusTarget || '');
        if (focusTarget) {
          focusTarget.focus();
        }
      });
    }
  };

})(Drupal, once);

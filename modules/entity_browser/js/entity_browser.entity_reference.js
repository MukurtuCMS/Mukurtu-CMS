/**
 * @file entity_browser.entity_reference.js
 *
 * Defines the behavior of the entity reference widget that utilizes entity
 * browser.
 */

(function ($, Drupal, Sortable) {

  'use strict';

  /**
   * Registers behaviours related to entity reference field widget.
   */
  Drupal.behaviors.entityBrowserEntityReference = {
    attach: function (context) {
      var sortableSelector = context.querySelectorAll('.field--widget-entity-browser-entity-reference .entities-list.sortable');
      sortableSelector.forEach(function (widget) {
        Sortable.create(widget, {
          draggable: '.item-container',
          onEnd: function onEnd() {
            return Drupal.entityBrowserEntityReference.entitiesReordered(widget);
          }
        });
      });
      // The AJAX callback will give us a flag when we need to re-open the
      // browser, most likely due to a "Replace" button being clicked.
      if (typeof drupalSettings.entity_browser_reopen_browser !== 'undefined' &&  drupalSettings.entity_browser_reopen_browser) {
        var data_drupal_selector = '[data-drupal-selector^="edit-' + drupalSettings.entity_browser_reopen_browser.replace(/_/g, '-') + '-entity-browser-entity-browser-' + '"][data-uuid]';
        var $launch_browser_element = $(context).find(data_drupal_selector);
        if ($launch_browser_element.attr('data-uuid') in drupalSettings.entity_browser && !drupalSettings.entity_browser[$launch_browser_element.attr('data-uuid')].auto_open) {
          $launch_browser_element.click();
        }
        // In case this is inside a fieldset closed by default, open it so the
        // user doesn't need to guess the browser is open but hidden there.
        var $fieldset_summary = $launch_browser_element.closest('details').find('summary');
        if ($fieldset_summary.length && $fieldset_summary.attr('aria-expanded') === 'false') {
          $fieldset_summary.click();
        }
      }
    }
  };

  Drupal.entityBrowserEntityReference = {};

  /**
   * Reacts on sorting of the entities.
   *
   * @param {object} widget
   *   Object with the sortable area.
   */
  Drupal.entityBrowserEntityReference.entitiesReordered = function (widget) {
    var items = $(widget).find('.item-container');
    var ids = [];
    for (var i = 0; i < items.length; i++) {
      ids[i] = $(items[i]).attr('data-entity-id');
    }

    $(widget).parent().find('input[type*=hidden][name*="[target_id]"]').val(ids.join(' '));
  };

}(jQuery, Drupal, Sortable));

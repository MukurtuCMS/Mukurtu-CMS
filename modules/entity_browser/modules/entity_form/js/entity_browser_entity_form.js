/**
 * @file entity_browser_entity_form.js
 *
 * Provides JS part of entity browser integration with IEF "use existing entity" feature.
 */

(function ($, Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Registers behaviours related to IEF "use existing" feature.
   */
  Drupal.behaviors.entityBrowserEntityForm = {
    attach: function (context) {
      $(once('ief-entity-browser-value', '.eb-target', context)).on('entity_browser_value_updated', Drupal.entityBrowserEntityForm.valuesUpdated);
    }
  };

  Drupal.entityBrowserEntityForm = {};

  /**
   * Reacts on entities being selected via entity form.
   */
  Drupal.entityBrowserEntityForm.valuesUpdated = function () {
    $(this).parent().find('.ief-entity-submit').trigger('entities-selected');
  };

}(jQuery, Drupal, drupalSettings, once));

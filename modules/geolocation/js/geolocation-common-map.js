/**
 * @file
 * Handle the common map.
 */

/**
 * @name CommonMapUpdateSettings
 * @property {String} enable
 * @property {String} hide_form
 * @property {number} views_refresh_delay
 * @property {String} update_view_id
 * @property {String} update_view_display_id
 * @property {String} boundary_filter
 * @property {String} parameter_identifier
 */

/**
 * @name CommonMapSettings
 * @property {Object} settings
 * @property {CommonMapUpdateSettings} dynamic_map
 * @property {Boolean} markerScrollToResult
 */

/**
 * @property {CommonMapSettings[]} drupalSettings.geolocation.commonMap
 */

(function ($, window, Drupal) {
  "use strict";

  /**
   * Attach common map style functionality.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationCommonMap = {
    /**
     * @param {GeolocationSettings} drupalSettings.geolocation
     */
    attach: function (context, drupalSettings) {
      if (typeof drupalSettings.geolocation === "undefined") {
        return;
      }

      $.each(
        drupalSettings.geolocation.commonMap,

        /**
         * @param {String} mapId - ID of current map
         * @param {CommonMapSettings} commonMapSettings - settings for current map
         */
        function (mapId, commonMapSettings) {
          var map = Drupal.geolocation.getMapById(mapId);

          if (!map) {
            return;
          }

          /*
           * Hide form if requested.
           */
          if (
            typeof commonMapSettings.dynamic_map !== "undefined" &&
            commonMapSettings.dynamic_map.enable &&
            commonMapSettings.dynamic_map.hide_form &&
            typeof commonMapSettings.dynamic_map.parameter_identifier !==
              "undefined"
          ) {
            var exposedForm = $(
              "form#views-exposed-form-" +
                commonMapSettings.dynamic_map.update_view_id.replace(
                  /_/g,
                  "-"
                ) +
                "-" +
                commonMapSettings.dynamic_map.update_view_display_id.replace(
                  /_/g,
                  "-"
                )
            );

            if (exposedForm.length === 1) {
              exposedForm
                .find(
                  'input[name^="' +
                    commonMapSettings.dynamic_map.parameter_identifier +
                    '"]'
                )
                .each(function (index, item) {
                  $(item).parent().hide();
                });

              // Hide entire form if it's empty now, except form-submit.
              if (
                exposedForm.find(
                  "input:visible:not(.form-submit), select:visible"
                ).length === 0
              ) {
                exposedForm.hide();
              }
            }
          }
        }
      );
    },
    detach: function (context, drupalSettings) {},
  };

  Drupal.geolocation.commonMap = Drupal.geolocation.commonMap || {};

  Drupal.geolocation.commonMap.dynamicMapViewsAjaxSettings = function (
    commonMapSettings
  ) {
    // Make sure to load current form DOM element, which will change after every AJAX operation.
    var view = $(
      ".view-id-" +
        commonMapSettings.dynamic_map.update_view_id +
        ".view-display-id-" +
        commonMapSettings.dynamic_map.update_view_display_id
    );
    if (view.length === 0) {
      console.error("Geolocation - No common map container found.");
      return;
    }

    if (typeof commonMapSettings.dynamic_map.boundary_filter === "undefined") {
      return;
    }

    // Extract the view DOM ID from the view classes.
    var matches = /(js-view-dom-id-\w+)/.exec(view.attr("class").toString());
    var currentViewId = matches[1].replace("js-view-dom-id-", "views_dom_id:");

    var viewInstance = Drupal.views.instances[currentViewId];
    var ajaxSettings = $.extend(true, {}, viewInstance.element_settings);
    ajaxSettings.progress.type = "none";

    var exposedForm = $(
      "form#views-exposed-form-" +
        commonMapSettings.dynamic_map.update_view_id.replace(/_/g, "-") +
        "-" +
        commonMapSettings.dynamic_map.update_view_display_id.replace(/_/g, "-")
    );
    if (exposedForm.length) {
      // Add form values.
      jQuery.each(exposedForm.serializeArray(), function (index, field) {
        var add = {};
        add[field.name] = field.value;
        ajaxSettings.submit = $.extend(ajaxSettings.submit, add);
      });
    }

    // Trigger geolocation bounds specific behavior.
    ajaxSettings.submit = $.extend(ajaxSettings.submit, {
      geolocation_common_map_dynamic_view: true,
    });

    return ajaxSettings;
  };
})(jQuery, window, Drupal);

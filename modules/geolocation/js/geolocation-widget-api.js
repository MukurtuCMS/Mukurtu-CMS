/**
 * @file
 * Javascript for widget API.
 */

/**
 * @param {GeolocationWidgetInterface[]} Drupal.geolocation.widgets - List of widget instances
 * @param {Object} Drupal.geolocation.widget - Prototype container
 * @param {GeolocationWidgetSettings[]} drupalSettings.geolocation.widgetSettings - Additional widget settings
 */

/**
 * @name GeolocationWidgetSettings
 * @property {String} autoClientLocationMarker
 * @property {jQuery} wrapper
 * @property {String} id
 * @property {String} type
 * @property {String} fieldName
 * @property {String} cardinality
 */

/**
 * Callback for location found or set by widget.
 *
 * @callback geolocationWidgetLocationCallback
 *
 * @param {String} Identifier
 * @param {GeolocationCoordinates} [location] - Location.
 * @param {int} [delta] - Delta.
 */

/**
 * Interface for classes that represent a color.
 *
 * @interface GeolocationWidgetInterface
 * @property {GeolocationWidgetSettings} settings
 * @property {String} id
 * @property {jQuery} wrapper
 * @property {jQuery} container
 * @property {geolocationWidgetLocationCallback[]} locationAlteredCallbacks
 *
 * @property {function({String}, {GeolocationCoordinates}|null, {int}|null)} locationAlteredCallback - Executes all {geolocationWidgetLocationCallback} modified callbacks.
 * @property {function({geolocationWidgetLocationCallback})} addLocationAlteredCallback - Adds a callback that will be called when a location is set.
 *
 * @property {function():{int}} getNextDelta - Get next delta.
 * @property {function({int}):{jQuery}} getInputByDelta - Get widget input by delta.
 *
 * @property {function({GeolocationCoordinates}, {int}=):{int}} setInput - Add or update input.
 * @property {function({int})} removeInput - Remove input.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * @namespace
   */

  Drupal.geolocation.widget = Drupal.geolocation.widget || {};

  Drupal.geolocation.widgets = Drupal.geolocation.widgets || [];

  Drupal.behaviors.geolocationWidgetApi = {
    attach: function (context, drupalSettings) {
      $.each(Drupal.geolocation.widgets, function (index, widget) {
        $.each(widget.pendingAddedInputs, function (inputIndex, inputData) {
          if (typeof inputData === "undefined") {
            return;
          }
          if (typeof inputData.delta === "undefined") {
            return;
          }
          var input = widget.getInputByDelta(inputData.delta);
          if (input) {
            widget.setInput(inputData.location, inputData.delta);
            widget.pendingAddedInputs.splice(inputIndex, 1);
          } else {
            widget.addNewEmptyInput();
          }
        });
      });
    },
  };

  /**
   * Geolocation widget.
   *
   * @constructor
   * @abstract
   * @implements {GeolocationWidgetInterface}
   *
   * @param {GeolocationWidgetSettings} widgetSettings - Setting to create widget.
   */
  function GeolocationMapWidgetBase(widgetSettings) {
    this.locationAlteredCallbacks = [];

    this.settings = widgetSettings || {};
    this.wrapper = widgetSettings.wrapper;
    this.fieldName = widgetSettings.fieldName;
    this.cardinality = widgetSettings.cardinality || 1;

    this.inputChangedEventPaused = false;

    this.id = widgetSettings.id;

    this.pendingAddedInputs = [];

    return this;
  }

  GeolocationMapWidgetBase.prototype = {
    locationAlteredCallback: function (identifier, location, delta) {
      if (typeof delta === "undefined" || delta === null) {
        delta = this.getNextDelta();
      }
      if (delta === false) {
        return;
      }
      $.each(this.locationAlteredCallbacks, function (index, callback) {
        callback(location, delta, identifier);
      });
    },
    addLocationAlteredCallback: function (callback) {
      this.locationAlteredCallbacks.push(callback);
    },
    getAllInputs: function () {
      return $(".geolocation-widget-input", this.wrapper);
    },
    refreshWidgetByInputs: function () {
      var that = this;
      this.getAllInputs().each(function (delta, inputElement) {
        var input = $(inputElement);
        var lng = input.find("input.geolocation-input-longitude").val();
        var lat = input.find("input.geolocation-input-latitude").val();
        var location;
        if (lng === "" || lat === "") {
          location = null;
        } else {
          location = {
            lat: Number(lat),
            lng: Number(lng),
          };
        }

        that.locationAlteredCallback("widget-refreshed", location, delta);
        that.attachInputChangedTriggers(input, delta);
      });
    },
    getInputByDelta: function (delta) {
      delta = parseInt(delta) || 0;
      var input = this.getAllInputs().eq(delta);
      if (input.length) {
        return input;
      }
      return null;
    },
    getCoordinatesByInput: function (input) {
      input = $(input);
      if (
        input.find("input.geolocation-input-longitude").val() !== "" &&
        input.find("input.geolocation-input-latitude").val() !== ""
      ) {
        return {
          lat: input.find("input.geolocation-input-latitude").val(),
          lng: input.find("input.geolocation-input-longitude").val(),
        };
      }
      return false;
    },
    getNextDelta: function () {
      if (this.cardinality === 1) {
        return 0;
      }

      var delta = Math.max(
        this.getNextEmptyInputDelta(),
        this.getNextPendingDelta()
      );
      if (delta >= this.cardinality && this.cardinality > 0) {
        console.error("Cannot add further geolocation input.");
        return false;
      }
      return delta;
    },
    getNextPendingDelta: function () {
      var maxDelta = this.pendingAddedInputs.length - 1;
      $.each(this.pendingAddedInputs, function (index, item) {
        if (typeof item.delta === "undefined") {
          return;
        }
        maxDelta = Math.max(maxDelta, item.delta);
      });

      return maxDelta + 1;
    },
    getNextEmptyInputDelta: function (delta) {
      if (this.cardinality === 1) {
        return 0;
      }

      if (typeof delta === "undefined") {
        delta = this.getAllInputs().length - 1;
      }

      var input = this.getInputByDelta(delta);

      // Current input not empty, return next delta.
      if (
        input.find("input.geolocation-input-longitude").val() ||
        input.find("input.geolocation-input-latitude").val()
      ) {
        return delta + 1;
      }

      // We reached the first input and it is empty, use it.
      if (delta === 0) {
        return 0;
      }

      // Recursively check for empty input.
      return this.getNextEmptyInputDelta(delta - 1);
    },
    addNewEmptyInput: function () {
      var button = this.wrapper.find(
        '[name="' + this.fieldName + '_add_more"]'
      );
      if (button.length) {
        button.trigger("mousedown");
      }
    },
    attachInputChangedTriggers: function (input, delta) {
      input = $(input);
      var that = this;
      var longitude = input.find("input.geolocation-input-longitude");
      var latitude = input.find("input.geolocation-input-latitude");

      longitude.off("change");
      longitude.change(function () {
        if (that.inputChangedEventPaused) {
          return;
        }

        var currentValue = $(this).val();
        if (currentValue === "") {
          that.locationAlteredCallback("input-altered", null, delta);
        } else if (latitude.val() !== "") {
          var location = {
            lat: Number(latitude.val()),
            lng: Number(currentValue),
          };
          that.locationAlteredCallback("input-altered", location, delta);
        }
      });

      latitude.off("change");
      latitude.change(function () {
        if (that.inputChangedEventPaused) {
          return;
        }

        var currentValue = $(this).val();
        if (currentValue === "") {
          that.locationAlteredCallback("input-altered", null, delta);
        } else if (longitude.val() !== "") {
          var location = {
            lat: Number(currentValue),
            lng: Number(longitude.val()),
          };
          that.locationAlteredCallback("input-altered", location, delta);
        }
      });
    },
    setInput: function (location, delta) {
      if (typeof delta === "undefined") {
        delta = this.getNextDelta();
      }

      if (typeof delta === "undefined" || delta === false) {
        console.error(
          location,
          Drupal.t("Could not determine delta for new widget input.")
        );
        return null;
      }

      var input = this.getInputByDelta(delta);
      if (input) {
        this.inputChangedEventPaused = true;
        input.find("input.geolocation-input-longitude").val(location.lng);
        input.find("input.geolocation-input-latitude").val(location.lat);
        this.inputChangedEventPaused = false;
      } else {
        this.pendingAddedInputs.push({
          delta: delta,
          location: location,
        });
        this.addNewEmptyInput();
      }

      return delta;
    },
    removeInput: function (delta) {
      var input = this.getInputByDelta(delta);
      this.inputChangedEventPaused = true;
      input.find("input.geolocation-input-longitude").val("");
      input.find("input.geolocation-input-latitude").val("");
      this.inputChangedEventPaused = false;
    },
  };

  Drupal.geolocation.widget.GeolocationMapWidgetBase = GeolocationMapWidgetBase;

  /**
   * Factory creating widget instances.
   *
   * @constructor
   *
   * @param {GeolocationWidgetSettings} widgetSettings - The widget settings.
   * @param {Boolean} [reset] Force creation of new widget.
   *
   * @return {GeolocationWidgetInterface|boolean} - New or updated widget.
   */
  function Factory(widgetSettings, reset) {
    reset = reset || false;
    widgetSettings.type = widgetSettings.type || "google";

    var widget = null;

    /**
     * Previously stored widget.
     * @type {boolean|GeolocationWidgetInterface}
     */
    var existingWidget = false;

    $.each(Drupal.geolocation.widgets, function (index, widget) {
      if (widget.id === widgetSettings.id) {
        existingWidget = Drupal.geolocation.widgets[index];
      }
    });

    if (reset === true || !existingWidget) {
      if (
        typeof Drupal.geolocation.widget[
          Drupal.geolocation.widget.widgetProviders[widgetSettings.type]
        ] !== "undefined"
      ) {
        var widgetProvider =
          Drupal.geolocation.widget[
            Drupal.geolocation.widget.widgetProviders[widgetSettings.type]
          ];
        widget = new widgetProvider(widgetSettings);
        if (widget) {
          Drupal.geolocation.widgets.push(widget);

          widget.refreshWidgetByInputs();
          widget.addLocationAlteredCallback(function (
            location,
            delta,
            identifier
          ) {
            if (
              identifier !== "input-altered" ||
              identifier !== "widget-refreshed"
            ) {
              if (location === null) {
                widget.removeInput(delta);
              } else {
                widget.setInput(location, delta);
              }
            }
          });
        }
      }
    } else {
      widget = existingWidget;
    }

    if (!widget) {
      console.error(widgetSettings, "Widget could not be initialzed"); // eslint-disable-line no-console .
      return false;
    }

    return widget;
  }

  Drupal.geolocation.widget.Factory = Factory;

  Drupal.geolocation.widget.widgetProviders = {};

  Drupal.geolocation.widget.addWidgetProvider = function (type, name) {
    Drupal.geolocation.widget.widgetProviders[type] = name;
  };

  /**
   * Get widget by ID.
   *
   * @param {String} id - Widget ID to retrieve.
   *
   * @return {GeolocationWidgetInterface|boolean} - Retrieved widget or false.
   */
  Drupal.geolocation.widget.getWidgetById = function (id) {
    var widget = false;
    $.each(Drupal.geolocation.widgets, function (index, currentWidget) {
      if (currentWidget.id === id) {
        widget = currentWidget;
      }
    });

    return widget;
  };
})(jQuery, Drupal);

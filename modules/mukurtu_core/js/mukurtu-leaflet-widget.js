(function ($, Drupal, once) {

  /**
   * Save location description.
   */
  Drupal.mukurtuSetLocationDescription = function (containerId, popupId) {
    Drupal.Leaflet[containerId].lMap._layers[popupId].feature.properties['location_description'] = $("#location-popup-" + popupId)[0].value;
    Drupal.Leaflet[containerId].lMap._layers[popupId].closePopup();
  };

  /**
   * Set the leaflet map object.
   */
  Drupal.Leaflet_Widget.prototype.set_leaflet_widget_map = function (map) {
    if (map !== undefined) {

      /* Copied from leaflet.widget.js begin: */

      this.map = map;
      map.addLayer(this.drawnItems);

      if (this.widgetsettings.scrollZoomEnabled) {
        map.on('focus', function () {
          map.scrollWheelZoom.enable();
        });
        map.on('blur', function () {
          map.scrollWheelZoom.disable();
        });
      }

      // Adjust toolbar to show defaultMarker or circleMarker.
      this.widgetsettings.toolbarSettings.drawMarker = false;
      this.widgetsettings.toolbarSettings.drawCircleMarker = false;
      if (this.widgetsettings.toolbarSettings.marker === "defaultMarker") {
        this.widgetsettings.toolbarSettings.drawMarker = 1;
      } else if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
        this.widgetsettings.toolbarSettings.drawCircleMarker = 1;
      }
      map.pm.addControls(this.widgetsettings.toolbarSettings);

      map.on('pm:create', function (event) {
        let layer = event.layer;
        this.drawnItems.addLayer(layer);
        layer.pm.enable({ allowSelfIntersection: false });
        this.update_text();
        // Listen to changes on the new layer
        this.add_layer_listeners(layer);
      }, this);

      // Start updating the Leaflet Map.
      this.update_leaflet_widget_map();

      // The parent locate call uses maxZoom from map config (now zoom 2 for
      // the fallback world view). When geolocation succeeds, fit the map to
      // the accuracy circle so zoom reflects actual precision. Registering
      // after update_leaflet_widget_map ensures our handler fires after
      // Leaflet's internal setView handler. Guard mirrors the parent's
      // value.length === 0 check so we don't register when data already exists.
      if (this.map_settings.locate && this.map_settings.locate.automatic &&
          !this.map_settings.map_position_force && this.get_json_value().length === 0) {
        map.once('locationfound', function(e) {
          var radius = Math.max(e.accuracy / 2, 0);
          map.fitBounds(e.latlng.toBounds(radius * 2), {maxZoom: 16});
        });
      }

      /* Copied from leaflet.widget.js end. */

      /* Mukurtu additions begin: */

      // Mukurtu Location Description pop-up.
      map.on('popupopen', function (event) {
        // Create the GeoJSON feature if it doesn't exist.
        if (!event.popup._source.feature) {
          event.popup._source.feature = event.popup._source.toGeoJSON();
        }
        // Populate the location description text box from the GeoJSON feature property.
        const locationDescription = event.popup._source.feature.properties['location_description'] ?? '';
        $('#location-popup-' + event.popup._source._leaflet_id).val(locationDescription);
      }, this);

    }
  };

  /**
   * Update the Leaflet Widget Map from value element.
   */
  Drupal.Leaflet_Widget.prototype.update_leaflet_widget_map = function () {
    const self = this;
    const value = this.get_json_value();

    /* Copied from leaflet.widget.js begin: */

    // Always clear the layers in drawnItems on map updates.
    this.drawnItems.clearLayers();

    // Apply styles to pm drawn items.
    this.map.pm.setGlobalOptions({
      pathOptions: this.widgetsettings.path_style
    });

    // Nothing to do if we don't have any data.
    if (value.length === 0) {
      // If no layer available, and the Map Center is not forced, locate the user position.
      if (this.map_settings.locate && this.map_settings.locate.automatic && !this.map_settings.map_position_force) {
        this.map.locate({setView: true, maxZoom: this.map_settings.zoom});
      }
      return;
    }

    try {
      const layerOpts = {
        style: function (feature) {
          return self.widgetsettings.path_style;
        }
      };

      // Use circleMarkers if specified.
      if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
        layerOpts.pointToLayer = function (feature, latlng) {
          return L.circleMarker(latlng);
        };
      }

      const obj = L.geoJson(JSON.parse(value), layerOpts);

      // See https://github.com/Leaflet/Leaflet.draw/issues/398
      obj.eachLayer(function(layer) {
        if (typeof layer.getLayers === "function") {
          const subLayers = layer.getLayers();
          for (let i = 0; i < subLayers.length; i++) {
            this.drawnItems.addLayer(subLayers[i]);
            this.add_layer_listeners(subLayers[i]);
          }
        }
        else {
          this.drawnItems.addLayer(layer);
          this.add_layer_listeners(layer);
        }
      }, this);

      // Pan the map to the feature
      if (this.widgetsettings.autoCenter) {
        let start_zoom;
        let start_center;

        if (obj.getBounds !== undefined && typeof obj.getBounds === 'function') {
          // For objects that have defined bounds or a way to get them
          const bounds = obj.getBounds();
          this.map.fitBounds(bounds);
          start_center = bounds.getCenter();

          // In case of Map Bounds collapsed into a Point or Map Zoom Forced,
          // use the custom Map Start Zoom (if set).
          if (this.widgetsettings.map_position.zoom &&
            (bounds.getSouthWest().distanceTo(bounds.getNorthEast()) === 0 || this.widgetsettings.map_position.force)) {
            /* Copied from leaflet.widget.js end. */

            /* Mukurtu additions begin: */

            // A single saved point should zoom in to a usable level, not
            // fall back to the empty-map default (deliberately zoomed out
            // to avoid world-map tiling on add forms - see #1453). An
            // explicit site-level "Force Map Center & Zoom" still wins,
            // matching contrib's documented behavior for that flag.
            start_zoom = (!this.widgetsettings.map_position.force && this.widgetsettings.map_position.singlePointZoom)
              ? this.widgetsettings.map_position.singlePointZoom
              : this.widgetsettings.map_position.zoom;

            /* Mukurtu additions end. */

            /* Copied from leaflet.widget.js begin: */
            this.map.setZoom(start_zoom);
          }
          else {
            // Update the map start zoom and center, for correct working of Map Reset control.
            start_zoom = this.map.getBoundsZoom(bounds);
          }
        } else if (obj.getLatLng !== undefined && typeof obj.getLatLng === 'function') {
          this.map.panTo(obj.getLatLng());
          // Update the map start center, for correct working of Map Reset control.
          start_center = this.map.getCenter();
          start_zoom = this.map.getZoom();
        }

        // In case of map initial position not forced, and zoomFiner not null/neutral,
        // adapt the Map Zoom and the Start Zoom accordingly.
        if (!this.widgetsettings.map_position.force &&
            this.widgetsettings.map_position.hasOwnProperty('zoomFiner') &&
            parseInt(this.widgetsettings.map_position.zoomFiner) !== 0) {
          start_zoom += parseFloat(this.widgetsettings.map_position.zoomFiner);
          this.map.setView(start_center, start_zoom);
        }

        // Reset the StartZoom and StartCenter.
        this.reset_start_zoom_and_center(this.mapid, start_zoom, start_center);
      }
    } catch (error) {
      if (window.console) console.error(error.message);
    }

    /* Copied from leaflet.widget.js end. */
  };

  /**
   * Add/Set Listeners to the Drawn Map Layers.
   */
  Drupal.Leaflet_Widget.prototype.add_layer_listeners = function (layer) {
    /* Mukurtu additions begin: */

    // Mukurtu Location Description.
    const containerId = $(layer._map._container).attr('id');
    const popupId = "location-popup-" + layer._leaflet_id;
    layer.bindPopup('<label for="' + popupId + '">' + Drupal.t('Map point description') + '</label><input class="mukurtu-leaflet-description-field" type="text" size="60" maxlength="255" id="' + popupId + '" onblur="Drupal.mukurtuSetLocationDescription(\'' + containerId + '\',' + layer._leaflet_id + ')"></input>');
    layer.on('popupclose', function (event) {
      this.update_text();
    }, this);

    /* Mukurtu additions end. */

    /* Copied from leaflet.widget.js begin: */

    // Listen to changes on the layer.
    layer.on('pm:edit', function (event) {
      this.update_text();
    }, this);

    // Listen to changes on the layer.
    layer.on('pm:update', function (event) {
      this.update_text();
    }, this);

    // Listen to drag events on the layer.
    layer.on('pm:dragend', function (event) {
      this.update_text();
    }, this);

    // Listen to cut events on the layer.
    layer.on('pm:cut', function (event) {
      this.drawnItems.removeLayer(event.originalLayer);
      this.drawnItems.addLayer(event.layer);
      this.update_text();
    }, this);

    // Listen to remove events on the layer.
    layer.on('pm:remove', function (event) {
      this.drawnItems.removeLayer(event.layer);
      this.update_text();
    }, this);

    /* Copied from leaflet.widget.js end. */
  };

  /**
   * Stop the geocoder search input's own clicks/drags from reaching the map.
   *
   * The contrib geocoder control never calls Leaflet's own
   * disableClickPropagation()/disableScrollPropagation() (unlike every
   * built-in Leaflet control), so double-clicking the input zooms the map
   * and dragging to select text pans it.
   */
  Drupal.behaviors.mukurtuLeafletGeocoderFix = {
    attach: function (context) {
      once('mukurtu-leaflet-geocoder-fix', '.leaflet-control-geocoder-container', context).forEach(function (container) {
        L.DomEvent.disableClickPropagation(container);
        const input = container.querySelector('input');
        if (input) {
          L.DomEvent.disableScrollPropagation(input);

          // Tag this input's own autocomplete dropdown so it can be widened
          // in CSS without affecting the many other jQuery UI autocomplete
          // fields (Place type, Location, etc.) sharing the same widget.
          const $input = $(input);
          const autocomplete = $input.autocomplete && $input.autocomplete('instance');
          if (autocomplete && autocomplete.menu) {
            autocomplete.menu.element.addClass('mukurtu-geocoder-autocomplete-menu');
          }
        }
      });
    }
  };

})(jQuery, Drupal, once);

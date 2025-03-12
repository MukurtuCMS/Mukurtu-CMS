(function ($, Drupal) {

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
   * Add/Set Listeners to the Drawn Map Layers.
   */
  Drupal.Leaflet_Widget.prototype.add_layer_listeners = function (layer) {
    /* Mukurtu additions begin: */

    // Mukurtu Location Description.
    const containerId = $(layer._map._container).attr('id');
    const popupId = "location-popup-" + layer._leaflet_id;
    layer.bindPopup('<label for="' + popupId + '">' + Drupal.t('Location Description') + '</label><input class="mukurtu-leaflet-description-field" type="text" size="60" maxlength="255" id="' + popupId + '" onblur="Drupal.mukurtuSetLocationDescription(\'' + containerId + '\',' + layer._leaflet_id + ')"></input>');
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

})(jQuery, Drupal);

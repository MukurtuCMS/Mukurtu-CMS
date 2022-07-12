(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.mukurtu_core_leaflet_widget = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var refresh = function () {

          $.each(settings.leaflet_widget, function (map_id, widgetSettings) {
            $('#' + map_id, context).each(function () {
              let map = $(this);
              let lMap = drupalSettings.leaflet[map_id].lMap;

              // Refreshes map data to load with correct size and bounds.
              lMap.invalidateSize();
              map.data('leaflet_widget', new Drupal.leaflet_widget(map, lMap, widgetSettings));
            });

          });
        };

        // Bind refresh function when changing horizontal tab.
        $('.horizontal-tabs-list').find('.horizontal-tab-button').each(function (key, tab) {
          $(tab).find('a').bind('click', refresh);
        });
      });
    }
  };

  /**
   * Save location description.
   */
  Drupal.mukurtuSetLocationDescription = function (id) {
    Drupal.Leaflet['leaflet-map'].lMap._layers[id].feature.properties['location_description'] = $("#location-popup-" + id)[0].value;
    Drupal.Leaflet['leaflet-map'].lMap._layers[id].closePopup();
  }


  /**
   * Set the leaflet map object.
   */
  Drupal.leaflet_widget.prototype.set_leaflet_map = function (map) {
    if (map !== undefined) {
      this.map = map;
      map.addLayer(this.drawnItems);

      if (this.settings.scrollZoomEnabled) {
        map.on('focus', function () {
          map.scrollWheelZoom.enable();
        });
        map.on('blur', function () {
          map.scrollWheelZoom.disable();
        });
      }

      // Adjust toolbar to show defaultMarker or circleMarker.
      this.settings.toolbarSettings.drawMarker = false;
      this.settings.toolbarSettings.drawCircleMarker = false;
      if (this.settings.toolbarSettings.marker === "defaultMarker") {
        this.settings.toolbarSettings.drawMarker = 1;
      } else if (this.settings.toolbarSettings.marker === "circleMarker") {
        this.settings.toolbarSettings.drawCircleMarker = 1;
      }
      map.pm.addControls(this.settings.toolbarSettings);

      map.on('pm:create', function (event) {
        let layer = event.layer;
        this.drawnItems.addLayer(layer);
        layer.pm.enable({ allowSelfIntersection: false });
        this.update_text();
        // Listen to changes on the new layer
        this.add_layer_listeners(layer);
      }, this);
      this.update_map();

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
  Drupal.leaflet_widget.prototype.add_layer_listeners = function (layer) {
    // Mukurtu Location Description.
    const popupId = "location-popup-" + layer._leaflet_id;
    layer.bindPopup('<label for="' + popupId + '">' + Drupal.t('Location Description') + '</label><input type="text" size="60" maxlength="255" id="' + popupId + '"></input><button type="button" onclick="Drupal.mukurtuSetLocationDescription(' + layer._leaflet_id + ')">' + Drupal.t('Save') + '</button>');
    layer.on('popupclose', function (event) {
      this.update_text();
    }, this);

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

  };

})(jQuery, Drupal, drupalSettings);

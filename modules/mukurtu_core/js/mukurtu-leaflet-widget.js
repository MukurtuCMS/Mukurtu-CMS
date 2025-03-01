(function ($, Drupal) {
  // These two prototype methods are overridden. Save the original methods so
  // that they can be called by the overridden versions.
  const original_set_leaflet_widget_map = Drupal.Leaflet_Widget.prototype.set_leaflet_widget_map;
  const original_add_layer_listeners = Drupal.Leaflet_Widget.prototype.add_layer_listeners;

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
    // Call the original set_leaflet_widget_map():
    original_set_leaflet_widget_map(map);

    // Mukurtu additions:
    if (map !== undefined) {
      this.map = map;

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
    // Call the original add_layer_listeners():
    original_add_layer_listeners(layer);

    // Mukurtu Location Description.
    const containerId = $(layer._map._container).attr('id');
    const popupId = "location-popup-" + layer._leaflet_id;
    layer.bindPopup('<label for="' + popupId + '">' + Drupal.t('Location Description') + '</label><input type="text" size="60" maxlength="255" id="' + popupId + '"></input><button type="button" onclick="Drupal.mukurtuSetLocationDescription(\'' + containerId + '\',' + layer._leaflet_id + ')">' + Drupal.t('Save') + '</button>');
    layer.on('popupclose', function (event) {
      this.update_text();
    }, this);

  };

})(jQuery, Drupal);

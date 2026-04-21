(function($, Drupal, drupalSettings) {

  /**
   * Serializes an object for URL query parameters.
   *
   * @param {Object} obj - The object to serialize.
   * @param {string} prefix - Optional prefix for nested parameters.
   * @return {string} The serialized query string.
   */
  Drupal.Leaflet.prototype.query_url_serialize = function(obj, prefix) {
    const str = [];
    for (const p in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, p)) {
        const k = prefix ? `${prefix}[${p}]` : p;
        const v = obj[p];
        str.push(
          (v !== null && typeof v === "object") ?
          Drupal.Leaflet.prototype.query_url_serialize(v, k) :
          `${encodeURIComponent(k)}=${encodeURIComponent(v)}`
        );
      }
    }
    return str.join("&");
  };

  /**
   * Performs geocoding using Drupal's geocoder API.
   *
   * @param {string} address - The address to geocode.
   * @param {string} providers - Comma-separated list of geocoder providers.
   * @param {Object} options - Additional options for the geocoder.
   * @return {Object} jQuery ajax promise.
   */
  Drupal.Leaflet.prototype.geocode = function(address, providers, options) {
    const base_url = drupalSettings.path.baseUrl;
    const geocode_path = `${base_url}geocoder/api/geocode`;
    const serializedOptions = Drupal.Leaflet.prototype.query_url_serialize(options);
    
    return $.ajax({
      url: `${geocode_path}?address=${encodeURIComponent(address)}&geocoder=${providers}&${serializedOptions}`,
      type: "GET",
      contentType: "application/json; charset=utf-8",
      dataType: "json",
    });
  };

  /**
   * Creates a geocoder control for Leaflet maps.
   *
   * @param {Object} controlDiv - The control container.
   * @param {string} mapid - The ID of the map.
   * @return {Object} L.Control instance.
   */
  Drupal.Leaflet.prototype.map_geocoder_control = function(controlDiv, mapid) {
    const geocoder_settings = drupalSettings.leaflet[mapid].map.settings.geocoder.settings;
    const control = new L.Control({position: geocoder_settings.position});
    
    control.onAdd = function() {
      const controlUI = L.DomUtil.create('div', 'geocoder leaflet-control-geocoder-container');
      controlUI.id = `${mapid}--leaflet-control-geocoder-container`;
      controlDiv.appendChild(controlUI);
      
      const autocomplete = geocoder_settings.autocomplete || {};
      const autocomplete_placeholder = autocomplete.placeholder || 'Search Address';
      const autocomplete_title = autocomplete.title || 'Search an Address on the Map';

      // Set CSS for the control search interior.
      const controlSearch = document.createElement('input');
      controlSearch.placeholder = Drupal.t(autocomplete_placeholder);
      controlSearch.id = `${mapid}--leaflet--geocoder-control`;
      controlSearch.title = Drupal.t(autocomplete_title);
      controlSearch.style.color = 'rgb(25,25,25)';
      controlSearch.style.padding = '0.2em 1em';
      controlSearch.style.borderRadius = '3px';
      controlSearch.size = geocoder_settings.input_size || 20;
      controlSearch.maxlength = 256;
      controlUI.appendChild(controlSearch);
      
      return controlUI;
    };
    
    return control;
  };

  /**
   * Adds autocomplete functionality to the geocoder control.
   *
   * @param {string} mapid - The ID of the map.
   * @param {Object} geocoder_settings - Settings for the geocoder.
   */
  Drupal.Leaflet.prototype.map_geocoder_control.autocomplete = function(mapid, geocoder_settings) {
    const providers = geocoder_settings.providers.toString();
    const options = geocoder_settings.options;
    const map = Drupal.Leaflet[mapid].lMap;
    const zoom = geocoder_settings.zoom || 14;
    const selector = $(`#${mapid}--leaflet--geocoder-control`);
    
    selector.autocomplete({
      autoFocus: true,
      minLength: geocoder_settings.min_terms || 4,
      delay: geocoder_settings.delay || 800,
      
      // This bit uses the geocoder to fetch address values.
      source: function(request, response) {
        const thisElement = this.element;
        thisElement.addClass('ui-autocomplete-loading');
        
        // Execute the geocoder.
        $.when(Drupal.Leaflet.prototype.geocode(request.term, providers, options).then(
          // On Resolve/Success.
          function(results) {
            response($.map(results, function(item) {
              thisElement.removeClass('ui-autocomplete-loading');
              return {
                // the value property is needed to be passed to the select.
                value: item.formatted_address,
                lat: item.geometry.location.lat,
                lng: item.geometry.location.lng
              };
            }));
          },
          // On Reject/Error.
          function() {
            thisElement.removeClass('ui-autocomplete-loading');
            response([]);
          }
        ));
      },
      
      // This bit is executed upon selection of an address.
      select: function(event, ui) {
        const position = L.latLng(ui.item.lat, ui.item.lng);
        map.setView(position, zoom);
        
        // If leaflet-geoman functionalities and controls existing on the map,
        // then disableGlobalEditMode;
        // if(map.pm) {
        //   map.pm.disableGlobalEditMode();
        // }
        
        const marker = L.marker(position);
        const popup = L.popup().setContent(ui.item.value);
        marker.bindPopup(popup);

        // In case of Place Marker on Geocode.
        if (geocoder_settings.set_marker && Drupal.Leaflet_Widget[mapid]) {
          Drupal.Leaflet_Widget[mapid].drawnItems.addLayer(marker);
          Drupal.Leaflet_Widget[mapid].update_text();
          Drupal.Leaflet_Widget[mapid].update_leaflet_widget_map();
          
          L.tooltip(position, {
            content: ui.item.value,
            direction: "bottom"
          }).addTo(map);
        }
        // Else, in case of Place Popup on Geocode.
        else if (geocoder_settings.popup) {
          L.popup()
            .setLatLng(position)
            .setContent(`<div class="leaflet-geocoder-popup">${ui.item.value}</div>`)
            .openOn(map);
        }
      }
    });
  };

})(jQuery, Drupal, drupalSettings);

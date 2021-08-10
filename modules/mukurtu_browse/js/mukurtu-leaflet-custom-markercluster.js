(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_leaflet_map = {
    attach: function (context, settings) {
      $(document).ready(function () {
        $(document, context).once('mukurtu_browse_leaflet_map').each(function () {
          // Extend Leaflet Clustering to Polygons.
          L.PolygonClusterable = L.Polygon.extend({
 /*            initialize: function () {
              this._latlng = this.getBounds().getCenter();
              L.Polygon.prototype.initialize.call(this);
            }, */

            getLatLng: function () {
              this._latlng = this.getBounds().getCenter();
              return this._latlng;
            },

            setLatLng: function () { }
          });

          // Initialize the map.
          Drupal.behaviors.mukurtu_browse_leaflet_map.features = [];
          var map = L.map('mukurtu-map-browse-map').setView([46.636236615519636, -117.37106323242186], 8);
          Drupal.behaviors.mukurtu_browse_leaflet_map.map = map;
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap contributors</a>'
          }).addTo(map);

          map.on('moveend', function() {
            if ('URLSearchParams' in window) {
              // Get the new leaflet bounding box.
              let bbox = this.getBounds().toBBoxString();

              // Update the URL query with the new bbox.
              let searchParams = new URLSearchParams(window.location.search);
              searchParams.set("bbox", bbox);
              /* window.location.search = searchParams.toString(); */
              let newQuery = window.location.pathname + '?' + searchParams.toString();
              history.pushState(null, '', newQuery);

              // Find the view to update the view args.
              let viewID = "";
              let classes = $('.view-display-id-mukurtu_browse_map_block')[0].classList;
              classes.forEach(function (element) {
                if (element.startsWith('js-view-dom-id-')) {
                  viewID = 'views_dom_id:' + element.replace('js-view-dom-id-', '');
                }
              });

              if (viewID != "") {
                // Update contextual filter with bounding box.
                Drupal.views.instances[viewID].settings["view_args"] = bbox;

                // Trigger the views' ajax update to redraw.
                $('.view-display-id-mukurtu_browse_map_block').trigger('RefreshView');
              }

              Drupal.behaviors.mukurtu_browse_leaflet_map.attachRefreshHandler();
            }
          });

          // Init clustering.
          Drupal.behaviors.mukurtu_browse_leaflet_map.markerClusterLayer = L.markerClusterGroup({
            disableClusteringAtZoom: 13,
            chunkedLoading: false,
          });

          // Find our view instance.
          let classes = $('.view-display-id-mukurtu_browse_map_block')[0].classList;
          classes.forEach(function (element) {
            if (element.startsWith('js-view-dom-id-')) {
              Drupal.behaviors.mukurtu_browse_leaflet_map.map_view_class = "." + element;
              viewID = 'views_dom_id:' + element.replace('js-view-dom-id-', '');
              Drupal.behaviors.mukurtu_browse_leaflet_map.map_dom_id = viewID;
            }
          });

          // Populate the map with our initial SAPI results.
          Drupal.behaviors.mukurtu_browse_leaflet_map.parseViewRowsToFeatures();

          // Add the marker cluster layer to the map.
          Drupal.behaviors.mukurtu_browse_leaflet_map.markerClusterLayer.addTo(map);
        });
      });
    },

    mukurtuClusterableGeoJSON: function (feature) {
      function geoJSONtoLeafletCoordinates(coordinates) {
        return coordinates.map(e => [e[1], e[0]]);
      }
      let points = [geoJSONtoLeafletCoordinates(feature.geometry.coordinates[0])];
      let layer = new L.PolygonClusterable(points);
      let popup = new DOMParser().parseFromString(feature.properties.popup, "text/html");
      let popupContent = popup.documentElement.textContent;
      layer.bindPopup(popupContent);
      layer.addTo(Drupal.behaviors.mukurtu_browse_leaflet_map.markerClusterLayer);

      return layer;
    },

    parseViewRowsToFeatures: function () {
      let freshFeatures = [];

      // Iterate through all the SAPI results.
      jQuery("#mukurtu-map-browse-container .views-element-container .view-content .views-row").each(function (index) {
        // Get the NID.
        let itemID = jQuery(this).find('.views-field-nid .field-content')[0].innerHTML.replace(/<!--.*?-->/sg, "").trim();

        // Get the cached GeoJSON for the location fields.
        let itemGeoJSON = jQuery(this).find('.views-field-field-mukurtu-geojson .field-content');

        if (itemGeoJSON[0] !== undefined) {
          // Remove HTML comments, used frequently for Drupal theme suggestion debuggging.
          let fieldValue = itemGeoJSON[0].innerHTML.replace(/<!--.*?-->/sg, "").trim();

          if (fieldValue.length > 0) {
            try {
              let features = JSON.parse(fieldValue);
              features.forEach(function (feature) {
                freshFeatures[itemID] = parseInt(itemID);
                // Render the feature if we haven't already.
                if (Drupal.behaviors.mukurtu_browse_leaflet_map.features[itemID] == undefined) {
                  Drupal.behaviors.mukurtu_browse_leaflet_map.features[itemID] = Drupal.behaviors.mukurtu_browse_leaflet_map.mukurtuClusterableGeoJSON(feature);
                }
              });
            } catch (e) {
              console.log(e);
            }
          }
        }
      });

      // Compare the "fresh" features with the previously rendered features and remove
      // any stale items.
      Drupal.behaviors.mukurtu_browse_leaflet_map.features.forEach(function (feature, index) {
        if (!freshFeatures.includes(index)) {
          // Remove this feature layer from the map.
          Drupal.behaviors.mukurtu_browse_leaflet_map.markerClusterLayer.removeLayer(Drupal.behaviors.mukurtu_browse_leaflet_map.features[index]);

          // Remove the feature from the list of features.
          delete Drupal.behaviors.mukurtu_browse_leaflet_map.features[index];
        }
      });
    },

    attachRefreshHandler: function () {
      Drupal.views.instances[Drupal.behaviors.mukurtu_browse_leaflet_map.map_dom_id].refreshViewAjax.success = Drupal.behaviors.mukurtu_browse_leaflet_map.refreshMap;
    },

    refreshMap: function (response, status) {
      // Call the parent success method.
      Drupal.Ajax.prototype.success.call(this, response, status);

      // Get the updated features.
      Drupal.behaviors.mukurtu_browse_leaflet_map.parseViewRowsToFeatures();
    }

  };
})(jQuery, Drupal);

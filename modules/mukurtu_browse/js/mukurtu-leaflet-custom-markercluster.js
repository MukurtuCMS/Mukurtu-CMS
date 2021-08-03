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
              return this._latlng
            },

            setLatLng: function () { }
          });

          // Initialize the map.
          var map = L.map('mukurtu-map-browse-map').setView([46.636236615519636, -117.37106323242186], 8);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap contributors</a>'
          }).addTo(map);

          // Init clustering.
          var markerClusterLayer = L.markerClusterGroup({
            disableClusteringAtZoom: 13,
            chunkedLoading: false,
          });

          function geoJSONtoLeafletCoordinates(coordinates) {
            return coordinates.map(e => [e[1], e[0]]);
          }

          function mukurtuClusterableGeoJSON(feature) {
            let points = [geoJSONtoLeafletCoordinates(feature.geometry.coordinates[0])];
            let layer = new L.PolygonClusterable(points);
            let popup = new DOMParser().parseFromString(feature.properties.popup, "text/html");
            let popupContent = popup.documentElement.textContent;
            layer.bindPopup(popupContent);
            layer.addTo(markerClusterLayer);
          }

          // Iterate through all the SAPI results.
          jQuery("#mukurtu-map-browse-container .views-element-container .view-content .views-row").each(function (index) {
            let poly = jQuery(this).find('.views-field-field-mukurtu-geojson .field-content');
            if (poly[0] !== undefined) {
              let fieldValue = poly[0].innerHTML.replace(/<!--.*?-->/sg, "").trim();

              if (fieldValue.length > 0) {
                try {
                  let features = JSON.parse(fieldValue);
                  features.forEach(function (feature) {
                    mukurtuClusterableGeoJSON(feature);
                  });
                } catch (e) {

                }
              }
            }
          });

          markerClusterLayer.addTo(map);
        });
      });
    }
  };
})(jQuery, Drupal);

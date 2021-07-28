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
/*               console.log("custom getlatlng: " + this.getBounds().getCenter());
              console.log(this); */
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
          markerClusterLayer = L.markerClusterGroup({
            disableClusteringAtZoom: 13,
            chunkedLoading: false,
          });

          function geoJSONtoLeafletCoordinates(coordinates) {
            return coordinates.map(e => [e[1], e[0]]);
          }

          // Iterate through all the SAPI results.
          jQuery("#mukurtu-map-browse-container .views-element-container .view-content .views-row").each(function (index) {
            let poly = jQuery(this).find('.views-field-field-coverage .field-content');
            if (poly[0] !== undefined) {
              let fieldValue = poly[0].innerHTML.replace(/<!--.*?-->/sg, "").trim();
              if (fieldValue.length > 0) {
                try {
                  let data = JSON.parse(fieldValue);
                  let points = [geoJSONtoLeafletCoordinates(data.coordinates[0])];
                  new L.PolygonClusterable(points).addTo(markerClusterLayer);
                } catch (e) {

                }
              }
            }
          });

          markerClusterLayer.addTo(map);
          /* // Testing marker clustering.
          var markers = L.markerClusterGroup();
          for (let i = 0; i < 1000; i++) {
            markers.addLayer(L.marker(getRandomLatLng(map)));
          }
          map.addLayer(markers); */
        });
      });
    }
  };
})(jQuery, Drupal);

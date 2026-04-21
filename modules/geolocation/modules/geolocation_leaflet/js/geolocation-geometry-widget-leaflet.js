/**
 * @file
 * Javascript for the geolocation geometry Leaflet widget.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Leaflet GeoJSON widget.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Function} layerToGeoJson
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Widget.
   */
  Drupal.behaviors.geolocationGeometryWidgetLeaflet = {
    getDrawSettingsByTyp: function /** @param {String} geometryType */ (geometryType) {
      switch (geometryType) {
        case 'polygon':
        case 'multipolygon':
          return {
            polyline: false,
            marker: false,
            circlemarker: false
          };

        case 'polyline':
        case 'multipolyline':
          return {
            polygon: false,
            rectangle: false,
            circle: false,
            marker: false,
            circlemarker: false
          };

        case 'point':
        case 'multipoint':
          return {
            polyline: false,
            polygon: false,
            rectangle: false,
            circle: false,
            circlemarker: false
          };

        default:
          return {
            circlemarker: false
          };
      }
    },
    layerToGeoJson:
      /**
       * @param {GeoJSON} layer
       * @param {String} geometryType
       */
      function (layer, geometryType) {
        var featureCollection = layer.toGeoJSON();

        switch (featureCollection.features.length) {
          case 0:
            return JSON.stringify('');

          case 1:
            return JSON.stringify(featureCollection.features[0].geometry);

          default:
            var types = {
              multipolygon: 'MultiPolygon',
              multipolyline: 'MultiPolyline',
              multipoint: 'MultiPoint',
              default: 'GeometryCollection'
            }

            var geometryCollection = {
              type: types[geometryType] || types['default'],
              geometries: []
            };

            featureCollection.features.forEach(function (feature) {
              geometryCollection.geometries.push(feature.geometry);
            });

            return JSON.stringify(geometryCollection);
        }
      },
    attach: function (context) {
      var thisBehavior = this;
      $(once('geolocation-geometry-processed', '.geolocation-geometry-widget-leaflet-geojson', context)).each(function (index, item) {
        var mapWrapper = $('.geolocation-geometry-widget-leaflet-geojson-map', item);
        var inputWrapper = $('.geolocation-geometry-widget-leaflet-geojson-input', item);
        var geometryType = $(item).data('geometryType');

        console.log(thisBehavior.getDrawSettingsByTyp(geometryType), geometryType + ' settings');

        var mapObject = Drupal.geolocation.getMapById(mapWrapper.attr('id').toString());

        mapObject.addPopulatedCallback(function /** @param {GeolocationLeafletMap} mapContainer */ (mapContainer) {

          var geoJsonLayer = L.geoJSON().addTo(mapContainer.leafletMap);
          var drawControl = new L.Control.Draw({
            draw: thisBehavior.getDrawSettingsByTyp(geometryType),
            edit: {
              featureGroup: geoJsonLayer
            }
          });
          mapContainer.leafletMap.addControl(drawControl);

          mapContainer.leafletMap.on(L.Draw.Event.CREATED, /** @param {Created} event */ function (event) {
            var layer = event.layer;
            geoJsonLayer.addLayer(layer);
            inputWrapper.val(thisBehavior.layerToGeoJson(geoJsonLayer, geometryType));
          });
          mapContainer.leafletMap.on(L.Draw.Event.EDITED, /** @param {Edited} event */ function (event) {
            inputWrapper.val(thisBehavior.layerToGeoJson(geoJsonLayer, geometryType));
          });
          mapContainer.leafletMap.on(L.Draw.Event.DELETED, /** @param {Deleted} event */ function (event) {
            inputWrapper.val(thisBehavior.layerToGeoJson(geoJsonLayer, geometryType));
          });

          if (inputWrapper.val()) {
            try {
              var geometry = JSON.parse(inputWrapper.val().toString());
              geoJsonLayer.addData(geometry);
            }
            catch (error) {
              console.error(error.message);
              return;
            }

            mapContainer.fitBoundaries(geoJsonLayer.getBounds(), 'geolocation_geometry_widget_leaflet');
          }
        });
      });
    },
    detach: function () {}
  };

})(jQuery, Drupal);

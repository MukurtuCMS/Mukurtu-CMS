/**
 * Re-override Drupal.Leaflet.prototype.add_features so that Views "grouping"
 * (used here to build a per field_coverage layer-toggle control) no longer
 * splits markers into separate MarkerClusterGroup instances. Leaflet only
 * clusters markers within the same MarkerClusterGroup, so nearby markers in
 * different groups previously never merged into one bubble (#1813). All
 * groups now share a single cluster group; the per-group layer toggle is
 * kept working by using each group's (otherwise empty) unclustered feature
 * group purely as a toggle proxy, adding/removing that group's markers from
 * the shared cluster group in response to Leaflet's native
 * overlayadd/overlayremove events.
 */

(function ($, Drupal) {
  Drupal.Leaflet.prototype.add_features = function (features, initial) {
    const leaflet_markercluster_options = this.map_settings.leaflet_markercluster.options && this.map_settings.leaflet_markercluster.options.length > 0 ? JSON.parse(this.map_settings.leaflet_markercluster.options) : {};
    const leaflet_markercluster_include_path = this.map_settings.leaflet_markercluster.include_path;

    // Single shared cluster/base-group pair for every feature, grouped or
    // not, so clustering spans the whole map.
    let layers = {
      unclustered: {
        _base: this.create_feature_group(),
      },
      clusters: {
        _base: new L.MarkerClusterGroup(leaflet_markercluster_options),
      },
    };

    // Markers belonging to each group label, so a group's toggle can
    // add/remove exactly its own markers from the shared cluster group.
    const groupMarkers = new Map();

    for (let i = 0; i < features.length; i++) {
      let feature = features[i];
      let lFeature;
      if (feature.group) {
        const label = feature['group_label'];
        // Purely a toggle proxy for the layer control - holds this group's
        // non-clustered (path) features, if any; its clustered markers live
        // in the shared cluster group instead, so clustering spans groups.
        const groupUnclustered = this.create_feature_group();
        const markers = [];

        for (let groupKey in feature.features) {
          let groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            if ((lFeature.setStyle && !lFeature.getRadius && !leaflet_markercluster_include_path) || groupFeature['markercluster_excluded']) {
              groupUnclustered.addLayer(lFeature);
            }
            else {
              markers.push(lFeature);
            }

            $(document).trigger('leaflet.feature', [lFeature, groupFeature, this, layers]);
          }
        }

        if (groupUnclustered.getLayers().length > 0 || markers.length > 0) {
          groupMarkers.set(label, markers);
          // add_overlay() only adds the layer to the map (via lMap.addLayer)
          // when it isn't hidden by default - it does not fire
          // overlayadd/overlayremove, so mirror that initial visibility here
          // by seeding the shared cluster group only for visible groups.
          if (!feature['disabled']) {
            markers.forEach((marker) => layers.clusters._base.addLayer(marker));
          }
          this.add_overlay(label, groupUnclustered, feature['disabled']);
        }
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          if ((lFeature.setStyle && !lFeature.getRadius && !leaflet_markercluster_include_path) || feature['markercluster_excluded']) {
            layers.unclustered._base.addLayer(lFeature);
          }
          else {
            layers.clusters._base.addLayer(lFeature);
          }

          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }
      }
    }

    this.add_overlay(null, L.featureGroup([layers.unclustered._base, layers.clusters._base]), false);

    // Keep each group's toggle working: since its clustered markers live in
    // the single shared cluster group rather than in the overlay layer
    // itself, showing/hiding the overlay has to add/remove them from the
    // cluster group by hand.
    this.lMap.off('overlayadd overlayremove').on('overlayadd overlayremove', (event) => {
      const markers = groupMarkers.get(event.name);
      if (!markers) {
        return;
      }
      if (event.type === 'overlayadd') {
        markers.forEach((marker) => layers.clusters._base.addLayer(marker));
      }
      else {
        markers.forEach((marker) => layers.clusters._base.removeLayer(marker));
      }
    });

    $(document).trigger('leaflet.features', [initial || false, this]);
  };
})(jQuery, Drupal);

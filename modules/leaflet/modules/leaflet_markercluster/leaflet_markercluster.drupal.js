/**
 * Override the adding features functionality of the Leaflet module,
 * with Marker Clustering logics.
 */

(function($, Drupal) {

  /**
   * Add Leaflet Features with Marker Clustering to the Leaflet Map.
   *
   * @param features
   *   Features List definition.
   * @param initial
   *   Boolean to identify initial status.
   */
  Drupal.Leaflet.prototype.add_features = function (features, initial) {
    const leaflet_markercluster_options = this.map_settings.leaflet_markercluster.options && this.map_settings.leaflet_markercluster.options.length > 0 ? JSON.parse(this.map_settings.leaflet_markercluster.options) : {};
    const leaflet_markercluster_include_path = this.map_settings.leaflet_markercluster.include_path;

    // Define Map Layers holder (both unclustered and clustered).
    let layers = {
      unclustered: {
        // Define a base Layer Group, to hold all (ungrouped) Features Layers.
        _base: this.create_feature_group()
      },
      clusters: {
        // Define a base Layer Cluster, to hold all (ungrouped) Clustered Layers.
        _base: new L.MarkerClusterGroup(leaflet_markercluster_options)
      }
    };

    for (let i = 0; i < features.length; i++) {
      let feature = features[i];
      let lFeature;
      // In case of a Features Group.
      if (feature.group) {
        // Define a named Layer Group, to hold all unClustered Features Layers.
        layers.unclustered[feature['group_label']] = this.create_feature_group();
        // Define a new Layer Group Cluster, to hold specific Group Layers.
        layers.clusters[feature['group_label']] = new L.MarkerClusterGroup(leaflet_markercluster_options);
        // Define every single Leaflet Feature of the Group.
        for (let groupKey in feature.features) {
          let groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            // If the Leaflet feature is extending the Path class (Polygon,
            // Polyline, Circle) don't add it to Markercluster if not requested,
            // and don't add it if specifically requested not to.
            if ((lFeature.setStyle && !lFeature.getRadius && !leaflet_markercluster_include_path) || groupFeature['markercluster_excluded']) {
              layers.unclustered[feature['group_label']].addLayer(lFeature);
            }
            else {
              // Add the single Leaflet Feature to the Layer Group Cluster.
              layers.clusters[feature['group_label']].addLayer(lFeature);
            }

            // Allow others to do something with the feature that was just added to the map
            $(document).trigger('leaflet.feature', [lFeature, groupFeature, this, layers]);
          }
        }

        // Add the Group Label Cluster Layer and/or the Group Label Base Layer as Overlay to the Map.
        if (layers.unclustered[feature['group_label']].getLayers().length > 0 || layers.clusters[feature['group_label']].getLayers().length > 0) {
          this.add_overlay(feature['group_label'], L.featureGroup([layers.unclustered[feature['group_label']], layers.clusters[feature['group_label']]]), feature['disabled']);
        }
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          // If the Leaflet feature is extending the Path class (Polygon,
          // Polyline, Circle) don't add it to Markercluster if not requested,
          // and don't add it if specifically requested not to.
          if ((lFeature.setStyle && !lFeature.getRadius && !leaflet_markercluster_include_path) || feature['markercluster_excluded']) {
            layers.unclustered._base.addLayer(lFeature);
          }
          else {
            layers.clusters._base.addLayer(lFeature);
          }

          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }
      }
    }

    // Add lBaseCluster to the map
    this.add_overlay(null, L.featureGroup([layers.unclustered._base, layers.clusters._base]), false);

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

})(jQuery, Drupal);

(function ($, Drupal) {

  const originalCreateGeometry = Drupal.Leaflet.prototype.create_geometry;

  /**
   * GeoJSON has no circle geometry type, so a drawn circle is stored (and
   * processed server-side by MukurtuLeafletService) as an N-sided polygon
   * approximation. MukurtuLeafletService restores the true center/radius
   * as a 'circle' typed feature when present; render it as a true L.circle
   * here instead of delegating to create_polygon, so it stays a
   * mathematically perfect vector at every zoom level rather than a
   * faceted polygon subject to Leaflet's zoom-dependent path
   * simplification (smoothFactor).
   */
  Drupal.Leaflet.prototype.create_geometry = function (feature, map_settings = false) {
    if (feature.type === 'circle') {
      const circle = new L.circle([feature.lat, feature.lon], { radius: feature.radius });

      // L.Circle.getBounds() projects through this._map, which only exists
      // once the layer has actually been added to a map. But
      // extend_map_bounds() (leaflet.drupal.js) calls getBounds() on every
      // non-point feature inside create_feature(), before add_features()
      // ever adds the returned layer anywhere - so on an unattached circle
      // it throws, aborting the rest of add_features() and leaving the map
      // fit to its fallback center/zoom instead of this feature. Approximate
      // geodesically (same formula Leaflet's own Circle._project() falls
      // back to for the edge case at door #2425) until it's actually
      // attached, then defer to the real, pixel-accurate implementation.
      const originalGetBounds = circle.getBounds.bind(circle);
      circle.getBounds = function () {
        if (this._map) {
          return originalGetBounds();
        }
        const center = this.getLatLng();
        const earthRadius = 6371000;
        const d = Math.PI / 180;
        const latR = (this.getRadius() / earthRadius) / d;
        const lngR = latR / Math.cos(center.lat * d);
        return L.latLngBounds(
          [center.lat - latR, center.lng - lngR],
          [center.lat + latR, center.lng + lngR]
        );
      };

      return circle;
    }
    return originalCreateGeometry.call(this, feature, map_settings);
  };

})(jQuery, Drupal);

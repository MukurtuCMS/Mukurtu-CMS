(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_leaflet_preview = {
    attach: function (context, settings) {
      $(document).ready(function () {
        let features = drupalSettings.leaflet["leaflet-map-view-mukurtu-map-browse-mukurtu-map-browse-block"].features;
        let lMap = drupalSettings.leaflet["leaflet-map-view-mukurtu-map-browse-mukurtu-map-browse-block"].lMap;

        var refreshPreviewFromLeaflet = function () {
          let inViewFeatures = [];
          let mapBounds = lMap.getBounds();

          // Determine which features are currently in the
          // visible map bounds.
          inViewFeatures = features.filter(
            function (e) {
              if (mapBounds.contains(e.points[0])) {
                return true;
              }
              return false;
            }
          );

          // Check if this is a different result set from current.
          let nids = inViewFeatures.map(e => e.entity_id);
          let currentNids = Drupal.behaviors.mukurtu_browse_leaflet_preview.currentTeaserNids ? Drupal.behaviors.mukurtu_browse_leaflet_preview.currentTeaserNids : [];

          // Compare the current teasers we have rendered with the new teasers.
          // We don't want to rerender if they are the same.
          let dirty = false;
          if (nids.length == currentNids.length) {
            var i = nids.length;
            while (i--) {
              if (nids[i] !== currentNids[i]) {
                dirty = true;
                break;
              }
            }
          } else {
            dirty = true;
          }

          // We need to rerender.
          if (dirty) {
            // Update our list of current teasers.
            Drupal.behaviors.mukurtu_browse_leaflet_preview.currentTeaserNids = nids;
            let nidsList = nids.join(',');

            // Make the AJAX call.
            try {
              Drupal.ajax({ url: '/browse/map/teasers/' + nidsList }).execute().done(
                function (comands, statusString, ajaxObject) {
              });
            } catch (error) {

            }
          }
        };

        // Listen for the moveend event and update the preview field.
        lMap.on("moveend", refreshPreviewFromLeaflet);
      });
    }
  };
})(jQuery, Drupal);

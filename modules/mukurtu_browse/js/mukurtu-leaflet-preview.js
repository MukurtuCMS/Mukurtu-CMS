(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_leaflet_preview = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var refreshPreviewFromLeaflet = function () {
          // Check if this is a different result set from current.
          let nids = Object.keys(Drupal.behaviors.mukurtu_browse_leaflet_map.features);
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
        //let leafletMap = getCurrentBrowseMap();
        Drupal.behaviors.mukurtu_browse_leaflet_map.map.on("moveend", refreshPreviewFromLeaflet);

        // Initialize Previews.
        refreshPreviewFromLeaflet();
      });
    }
  };
})(jQuery, Drupal);

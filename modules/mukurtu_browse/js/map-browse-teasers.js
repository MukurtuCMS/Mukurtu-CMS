(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_leaflet_preview = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var refreshPreviewFromLeaflet = function () {
          // Find the current map.
          let browseMap = undefined;
          let viewInstances = Object.keys(Drupal.views.instances);
          for (const instanceKey of viewInstances) {
            if (Drupal.views.instances[instanceKey].settings.view_name == 'mukurtu_browse_by_map') {
              let viewSelector = Drupal.views.instances[instanceKey].element_settings.selector;
              let mapId = $(viewSelector + ' .view-content > div').attr('id');
              if (mapId) {
                browseMap = drupalSettings.leaflet[mapId];
              }
            }
          }

          // Check if this is a different result set from current.
          let nids = browseMap.features.map(feature => feature.entity_id);
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

        /**
         * Load the node content when a popup is opened.
         */
        var loadEntityTeaser = function (popup) {
          // Find the nid from the popup.
          popupContent = $('<div>');
          popupContent.html(popup.popup._contentNode.innerHTML);
          let nid = popupContent.find('div.nid')[0].innerHTML;

          // Check if this node is already the current preview,
          // we don't want to rerender.
          if (nid == Drupal.behaviors.mukurtu_browse_leaflet_preview.currentNid) {
            return;
          }

          // Set node as the current preview.
          Drupal.behaviors.mukurtu_browse_leaflet_preview.currentNid = nid;
          // Make the AJAX call to load and render the preview.
          try {
            Drupal.ajax({ url: '/browse/map/teaser/' + nid }).execute().done(
              function (comands, statusString, ajaxObject) {
            });
          } catch (error) {

          }
        }

        // Find the current map.
        let browseMap = undefined;
        let viewInstances = Object.keys(Drupal.views.instances);
        for (const instanceKey of viewInstances) {
          if (Drupal.views.instances[instanceKey].settings.view_name == 'mukurtu_browse_by_map') {
            let viewSelector = Drupal.views.instances[instanceKey].element_settings.selector;
            let mapId = $(viewSelector + ' .view-content > div').attr('id');
            if (mapId) {
              browseMap = drupalSettings.leaflet[mapId];
            }
          }
        }
        //let browseMap = drupalSettings.leaflet['leaflet-map-view-mukurtu-browse-by-map-map-block'];
        //browseMap.lMap.on("moveend", refreshPreviewFromLeaflet);
        browseMap.lMap.on('popupopen', loadEntityTeaser);

        // Initialize Previews.
        //refreshPreviewFromLeaflet();
      });
    }
  };
})(jQuery, Drupal);

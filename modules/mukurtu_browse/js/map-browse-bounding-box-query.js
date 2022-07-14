(function ($, Drupal) {
  Drupal.behaviors.mukurtu_browse_leaflet_map = {
    attach: function (context, settings) {
      $(document).ready(function () {
        $(document, context).once('mukurtu_browse_leaflet_map').each(function () {
          let map = drupalSettings.leaflet['leaflet-map-view-mukurtu-browse-by-map-map-block'];
          map.lMap.on('moveend', function () {
            if ('URLSearchParams' in window) {
              // Get the new leaflet bounding box.
              let bbox = this.getBounds().toBBoxString();

              // Update the URL query with the new bbox.
              let searchParams = new URLSearchParams(window.location.search);
              searchParams.set("bbox", bbox);

              let newQuery = window.location.pathname + '?' + searchParams.toString();
              history.pushState(null, '', newQuery);

              let viewInstances = Object.keys(drupalSettings.views.ajaxViews);
              for (const instanceKey of viewInstances) {
                if (drupalSettings.views.ajaxViews[instanceKey].view_name == 'mukurtu_browse_by_map') {
                  //drupalSettings.views.ajaxViews[instanceKey].view_args = bbox;
                }
              }

              // Trigger the views' ajax update to redraw.
              //$('.view-id-mukurtu_browse_by_map.view-display-id-map_block').trigger('RefreshView');
            }
          });
        });
      });
    }

  }
})(jQuery, Drupal);

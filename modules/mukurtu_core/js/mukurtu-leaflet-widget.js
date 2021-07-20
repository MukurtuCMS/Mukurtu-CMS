(function ($, Drupal) {
  Drupal.behaviors.mukurtu_core_leaflet_widget = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var refresh = function () {

          $.each(settings.leaflet_widget, function (map_id, widgetSettings) {
            $('#' + map_id, context).each(function () {
              let map = $(this);
              let lMap = drupalSettings.leaflet[map_id].lMap;

              // Refreshes map data to load with correct size and bounds.
              lMap.invalidateSize();
              map.data('leaflet_widget', new Drupal.leaflet_widget(map, lMap, widgetSettings));
            });

          });
        };

        // Bind refresh function when changing horizontal tab.
        $('.horizontal-tabs-list').find('.horizontal-tab-button').each(function (key, tab) {
          $(tab).find('a').bind('click', refresh);
        });
      });
    }
  };
})(jQuery, Drupal);

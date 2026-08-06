(function ($, Drupal) {

  /**
   * Save location description.
   */
  Drupal.mukurtuSetLocationDescription = function (containerId, popupId) {
    Drupal.Leaflet[containerId].lMap._layers[popupId].feature.properties['location_description'] = $("#location-popup-" + popupId)[0].value;
    Drupal.Leaflet[containerId].lMap._layers[popupId].closePopup();
  };

  /**
   * Copies Geoman toolbar buttons' existing (but non-focusable-element)
   * "title" text onto the actual focusable button as an aria-label, since
   * Geoman puts the title on a wrapping div rather than the focusable
   * a[role="button"] inside it. Re-run whenever Geoman rebuilds/toggles
   * the toolbar, since it re-renders buttons on mode changes.
   */
  Drupal.mukurtuLabelGeomanToolbar = function (map) {
    const $container = $(map.getContainer());
    $container.find('.button-container[title]').each(function () {
      const $button = $(this).find('a.leaflet-buttons-control-button, a.leaflet-pm-action').first();
      if ($button.length && !$button.attr('aria-label')) {
        $button.attr('aria-label', $(this).attr('title'));
      }
    });
    $container.find('a.leaflet-pm-action[title]').each(function () {
      if (!$(this).attr('aria-label')) {
        $(this).attr('aria-label', $(this).attr('title'));
      }
    });
  };

  /**
   * Set the leaflet map object.
   */
  Drupal.Leaflet_Widget.prototype.set_leaflet_widget_map = function (map) {
    if (map !== undefined) {

      /* Copied from leaflet.widget.js begin: */

      this.map = map;
      map.addLayer(this.drawnItems);

      if (this.widgetsettings.scrollZoomEnabled) {
        map.on('focus', function () {
          map.scrollWheelZoom.enable();
        });
        map.on('blur', function () {
          map.scrollWheelZoom.disable();
        });
      }

      // Adjust toolbar to show defaultMarker or circleMarker.
      this.widgetsettings.toolbarSettings.drawMarker = false;
      this.widgetsettings.toolbarSettings.drawCircleMarker = false;
      if (this.widgetsettings.toolbarSettings.marker === "defaultMarker") {
        this.widgetsettings.toolbarSettings.drawMarker = 1;
      } else if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
        this.widgetsettings.toolbarSettings.drawCircleMarker = 1;
      }
      map.pm.addControls(this.widgetsettings.toolbarSettings);

      /* Mukurtu accessibility additions begin: */

      // Geoman ships Escape-to-exit and Enter-to-finish keyboard support,
      // but both default to off. setGlobalOptions() deep-merges, so this
      // survives the later pathOptions-setting call elsewhere.
      map.pm.setGlobalOptions({ exitModeOnEscape: true, finishOnEnter: true });

      const containerId = $(map.getContainer()).attr('id');
      const instructionsId = containerId + '-keyboard-instructions';
      map.getContainer().setAttribute('aria-label', Drupal.t('Interactive location map'));
      if ($('#' + instructionsId).length) {
        map.getContainer().setAttribute('aria-describedby', instructionsId);
      }

      Drupal.mukurtuLabelGeomanToolbar(map);
      map.on('pm:globaldrawmodetoggled pm:globaleditmodetoggled pm:globaldragmodetoggled pm:globalremovalmodetoggled pm:globalcutmodetoggled pm:globalrotatemodetoggled', function () {
        Drupal.mukurtuLabelGeomanToolbar(map);
      });

      // Move the location-description input's onblur handler to a
      // delegated listener (CSP-friendlier than an inline attribute, and
      // avoids re-attaching it for every marker).
      $(map.getContainer()).on('focusout', '.mukurtu-leaflet-description-field', function () {
        const popupId = $(this).attr('id').replace('location-popup-', '');
        Drupal.mukurtuSetLocationDescription(containerId, popupId);
      });

      /* Mukurtu accessibility additions end. */

      map.on('pm:create', function (event) {
        let layer = event.layer;
        this.drawnItems.addLayer(layer);
        layer.pm.enable({ allowSelfIntersection: false });
        this.update_text();
        // Listen to changes on the new layer
        this.add_layer_listeners(layer);
        Drupal.announce(Drupal.t('Shape added to the map.'));
      }, this);

      // Start updating the Leaflet Map.
      this.update_leaflet_widget_map();

      // The parent locate call uses maxZoom from map config (now zoom 2 for
      // the fallback world view). When geolocation succeeds, fit the map to
      // the accuracy circle so zoom reflects actual precision. Registering
      // after update_leaflet_widget_map ensures our handler fires after
      // Leaflet's internal setView handler. Guard mirrors the parent's
      // value.length === 0 check so we don't register when data already exists.
      if (this.map_settings.locate && this.map_settings.locate.automatic &&
          !this.map_settings.map_position_force && this.get_json_value().length === 0) {
        map.once('locationfound', function(e) {
          var radius = Math.max(e.accuracy / 2, 0);
          map.fitBounds(e.latlng.toBounds(radius * 2), {maxZoom: 16});
        });
      }

      /* Copied from leaflet.widget.js end. */

      /* Mukurtu additions begin: */

      // Mukurtu Location Description pop-up.
      map.on('popupopen', function (event) {
        // Create the GeoJSON feature if it doesn't exist.
        if (!event.popup._source.feature) {
          event.popup._source.feature = event.popup._source.toGeoJSON();
        }
        // Populate the location description text box from the GeoJSON feature property.
        const locationDescription = event.popup._source.feature.properties['location_description'] ?? '';
        const $input = $('#location-popup-' + event.popup._source._leaflet_id);
        $input.val(locationDescription);
        // Move focus into the popup for keyboard users - Leaflet opens it
        // without moving focus off the marker.
        $input.trigger('focus');
        // Translate Leaflet's hardcoded English close-button label.
        $(event.popup._container).find('.leaflet-popup-close-button')
          .attr('aria-label', Drupal.t('Close popup'))
          .attr('title', Drupal.t('Close popup'));
      }, this);

    }
  };

  /**
   * Add/Set Listeners to the Drawn Map Layers.
   */
  Drupal.Leaflet_Widget.prototype.add_layer_listeners = function (layer) {
    /* Mukurtu additions begin: */

    // Mukurtu Location Description.
    const popupId = "location-popup-" + layer._leaflet_id;

    // Give the marker icon a meaningful accessible name instead of
    // Leaflet's hardcoded, untranslated alt="Marker".
    const updateMarkerLabel = function () {
      const description = (layer.feature && layer.feature.properties) ? layer.feature.properties['location_description'] : '';
      if (layer._icon) {
        layer._icon.setAttribute('alt', description || Drupal.t('Map point'));
        layer._icon.setAttribute('aria-label', description || Drupal.t('Map point'));
      }
    };
    updateMarkerLabel();

    layer.bindPopup('<label for="' + popupId + '">' + Drupal.t('Map point description') + '</label><input class="mukurtu-leaflet-description-field" type="text" size="60" maxlength="255" id="' + popupId + '"></input>');
    layer.on('popupclose', function (event) {
      this.update_text();
      updateMarkerLabel();
      // Return focus to the marker so keyboard users aren't dropped
      // somewhere unexpected when the popup closes.
      if (layer._icon) {
        $(layer._icon).trigger('focus');
      }
    }, this);

    /* Mukurtu additions end. */

    /* Copied from leaflet.widget.js begin: */

    // Listen to changes on the layer.
    layer.on('pm:edit', function (event) {
      this.update_text();
    }, this);

    // Listen to changes on the layer.
    layer.on('pm:update', function (event) {
      this.update_text();
    }, this);

    // Listen to drag events on the layer.
    layer.on('pm:dragend', function (event) {
      this.update_text();
    }, this);

    // Listen to cut events on the layer.
    layer.on('pm:cut', function (event) {
      this.drawnItems.removeLayer(event.originalLayer);
      this.drawnItems.addLayer(event.layer);
      this.update_text();
    }, this);

    // Listen to remove events on the layer.
    layer.on('pm:remove', function (event) {
      this.drawnItems.removeLayer(event.layer);
      this.update_text();
      Drupal.announce(Drupal.t('Shape removed from the map.'));
    }, this);

    /* Copied from leaflet.widget.js end. */
  };

})(jQuery, Drupal);

(function ($, Drupal, once) {

  /**
   * Save location description.
   */
  Drupal.mukurtuSetLocationDescription = function (containerId, popupId) {
    Drupal.Leaflet[containerId].lMap._layers[popupId].feature.properties['location_description'] = $("#location-popup-" + popupId)[0].value;
    Drupal.Leaflet[containerId].lMap._layers[popupId].closePopup();
  };

  /**
   * GeoJSON has no circle geometry, so a circle layer serializes itself as
   * a polygon approximating its shape, storing its true center/radius as
   * custom Feature properties so it can be rebuilt as an editable circle
   * the next time the widget loads (see add_layer_listeners below).
   */
  Drupal.mukurtuPatchCircleToGeoJSON = function (circleLayer) {
    circleLayer.toGeoJSON = function () {
      const polygon = L.PM.Utils.circleToPolygon(this, 128);
      const geoJson = polygon.toGeoJSON();
      const center = this.getLatLng();
      geoJson.properties = Object.assign(
        {},
        this.feature && this.feature.properties,
        {
          circle_center: [center.lat, center.lng],
          circle_radius: this.getRadius()
        }
      );
      return geoJson;
    };
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
        if (event.shape === 'Circle') {
          // Keep the drawn layer a true, editable circle; only its
          // serialized form becomes a polygon approximation.
          Drupal.mukurtuPatchCircleToGeoJSON(layer);
        }
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

      // update_leaflet_widget_map() just ran above, but if this map starts
      // out inside a hidden tab/details pane (e.g. a field_group "Additional
      // Fields" tab that isn't active yet), its container is still 0x0, so
      // the fitBounds()/singlePointZoom correction it just computed is
      // meaningless and gets thrown away. Contrib's own visibility handling
      // (leaflet.drupal.js) only calls invalidateSize() the first time the
      // container becomes visible - it never redoes the fit/zoom - so once
      // the tab is revealed the map is stuck showing the empty-map default
      // instead of the saved point. Redo our fit/zoom once the container
      // actually has real dimensions.
      if ('IntersectionObserver' in window) {
        const self = this;
        const visibilityObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.intersectionRatio > 0) {
              map.invalidateSize();
              self.update_leaflet_widget_map();
              visibilityObserver.disconnect();
            }
          });
        });
        visibilityObserver.observe(map.getContainer());
      }

    }
  };

  /**
   * Update the Leaflet Widget Map from value element.
   */
  Drupal.Leaflet_Widget.prototype.update_leaflet_widget_map = function () {
    const self = this;
    const value = this.get_json_value();

    /* Copied from leaflet.widget.js begin: */

    // Always clear the layers in drawnItems on map updates.
    this.drawnItems.clearLayers();

    // Apply styles to pm drawn items.
    this.map.pm.setGlobalOptions({
      pathOptions: this.widgetsettings.path_style
    });

    // Nothing to do if we don't have any data.
    if (value.length === 0) {
      // If no layer available, and the Map Center is not forced, locate the user position.
      if (this.map_settings.locate && this.map_settings.locate.automatic && !this.map_settings.map_position_force) {
        this.map.locate({setView: true, maxZoom: this.map_settings.zoom});
      }
      return;
    }

    try {
      const layerOpts = {
        style: function (feature) {
          return self.widgetsettings.path_style;
        }
      };

      // Use circleMarkers if specified.
      if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
        layerOpts.pointToLayer = function (feature, latlng) {
          return L.circleMarker(latlng);
        };
      }

      const obj = L.geoJson(JSON.parse(value), layerOpts);

      // See https://github.com/Leaflet/Leaflet.draw/issues/398
      obj.eachLayer(function(layer) {
        if (typeof layer.getLayers === "function") {
          const subLayers = layer.getLayers();
          for (let i = 0; i < subLayers.length; i++) {
            this.drawnItems.addLayer(subLayers[i]);
            this.add_layer_listeners(subLayers[i]);
          }
        }
        else {
          this.drawnItems.addLayer(layer);
          this.add_layer_listeners(layer);
        }
      }, this);

      // Pan the map to the feature
      if (this.widgetsettings.autoCenter) {
        let start_zoom;
        let start_center;

        if (obj.getBounds !== undefined && typeof obj.getBounds === 'function') {
          // For objects that have defined bounds or a way to get them
          const bounds = obj.getBounds();
          this.map.fitBounds(bounds);
          start_center = bounds.getCenter();

          // In case of Map Bounds collapsed into a Point or Map Zoom Forced,
          // use the custom Map Start Zoom (if set).
          if (this.widgetsettings.map_position.zoom &&
            (bounds.getSouthWest().distanceTo(bounds.getNorthEast()) === 0 || this.widgetsettings.map_position.force)) {
            /* Copied from leaflet.widget.js end. */

            /* Mukurtu additions begin: */

            // A single saved point should zoom in to a usable level, not
            // fall back to the empty-map default (deliberately zoomed out
            // to avoid world-map tiling on add forms - see #1453). An
            // explicit site-level "Force Map Center & Zoom" still wins,
            // matching contrib's documented behavior for that flag.
            start_zoom = (!this.widgetsettings.map_position.force && this.widgetsettings.map_position.singlePointZoom)
              ? this.widgetsettings.map_position.singlePointZoom
              : this.widgetsettings.map_position.zoom;

            /* Mukurtu additions end. */

            /* Copied from leaflet.widget.js begin: */
            this.map.setZoom(start_zoom);
          }
          else {
            // Update the map start zoom and center, for correct working of Map Reset control.
            start_zoom = this.map.getBoundsZoom(bounds);
          }
        } else if (obj.getLatLng !== undefined && typeof obj.getLatLng === 'function') {
          this.map.panTo(obj.getLatLng());
          // Update the map start center, for correct working of Map Reset control.
          start_center = this.map.getCenter();
          start_zoom = this.map.getZoom();
        }

        // In case of map initial position not forced, and zoomFiner not null/neutral,
        // adapt the Map Zoom and the Start Zoom accordingly.
        if (!this.widgetsettings.map_position.force &&
            this.widgetsettings.map_position.hasOwnProperty('zoomFiner') &&
            parseInt(this.widgetsettings.map_position.zoomFiner) !== 0) {
          start_zoom += parseFloat(this.widgetsettings.map_position.zoomFiner);
          this.map.setView(start_center, start_zoom);
        }

        // Reset the StartZoom and StartCenter.
        this.reset_start_zoom_and_center(this.mapid, start_zoom, start_center);
      }
    } catch (error) {
      if (window.console) console.error(error.message);
    }

    /* Copied from leaflet.widget.js end. */
  };

  /**
   * Add/Set Listeners to the Drawn Map Layers.
   */
  Drupal.Leaflet_Widget.prototype.add_layer_listeners = function (layer) {
    /* Mukurtu additions begin: */

    // A polygon loaded from storage that carries circle_center/circle_radius
    // properties was originally drawn as a circle; rebuild it as one so it's
    // editable (drag center, resize radius) rather than a vertex outline.
    if (layer.feature && layer.feature.properties && layer.feature.properties.circle_center) {
      const props = layer.feature.properties;
      // layer.options carries the widget's generic path style, which
      // includes a radius meant only for circleMarker point styling
      // (see LeafletDefaultWidget's default 'path' setting). Spread it
      // first so the real circle_radius always wins.
      const circleLayer = L.circle(props.circle_center, {
        ...layer.options,
        radius: props.circle_radius
      });
      circleLayer.feature = layer.feature;
      Drupal.mukurtuPatchCircleToGeoJSON(circleLayer);
      this.drawnItems.removeLayer(layer);
      this.drawnItems.addLayer(circleLayer);
      layer = circleLayer;
    }

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

    // Disable fill for open polylines, same rationale as the display-side
    // fix in LeafletHooks.php (issue #732): lines aren't areas, only
    // polygons should keep the configured fill. Circles are out of scope
    // (issue #849). L.Polygon extends L.Polyline, so it must be excluded
    // explicitly.
    if (!(layer instanceof L.Polygon) && layer instanceof L.Polyline) {
      layer.setStyle({ fill: false });
    }

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

  /**
   * Stop the geocoder search input's own clicks/drags from reaching the map.
   *
   * The contrib geocoder control never calls Leaflet's own
   * disableClickPropagation()/disableScrollPropagation() (unlike every
   * built-in Leaflet control), so double-clicking the input zooms the map
   * and dragging to select text pans it.
   */
  Drupal.behaviors.mukurtuLeafletGeocoderFix = {
    attach: function (context) {
      once('mukurtu-leaflet-geocoder-fix', '.leaflet-control-geocoder-container', context).forEach(function (container) {
        L.DomEvent.disableClickPropagation(container);
        const input = container.querySelector('input');
        if (input) {
          L.DomEvent.disableScrollPropagation(input);

          // The contrib control passes its default placeholder and title to
          // Drupal.t() through variables, so string extraction never finds
          // them and they can't be translated. When the defaults are in use,
          // set them again through literal Drupal.t() calls.
          if (input.placeholder === 'Search Address') {
            input.placeholder = Drupal.t('Search Address');
          }
          if (input.title === 'Search an Address on the Map') {
            input.title = Drupal.t('Search an Address on the Map');
          }

          // Keep the search box clear of the zoom buttons on a narrow map
          // (see the input rule in the CSS). The map can start hidden in a
          // collapsed group, so measure whenever its size changes.
          const map = container.closest('.leaflet-container');
          const zoom = map && map.querySelector('.leaflet-control-zoom');
          if (zoom && window.ResizeObserver) {
            new ResizeObserver(function () {
              const mapRect = map.getBoundingClientRect();
              if (!mapRect.width) {
                return;
              }
              const space = container.getBoundingClientRect().right - zoom.getBoundingClientRect().right - 10;
              input.style.setProperty('--mukurtu-geocoder-input-limit', Math.max(space, 0) + 'px');
            }).observe(map);
          }

          // The contrib control names the input only with its title ("Search
          // an Address on the Map"), which doesn't contain the visible
          // placeholder text, so speech users can't target it by what they
          // see (WCAG 2.5.3). Reuse the placeholder so the two stay in step,
          // translations included.
          if (input.placeholder && !input.hasAttribute('aria-label')) {
            input.setAttribute('aria-label', input.placeholder);
          }

          // Tag this input's own autocomplete dropdown so it can be widened
          // in CSS without affecting the many other jQuery UI autocomplete
          // fields (Place type, Location, etc.) sharing the same widget.
          const $input = $(input);
          const autocomplete = $input.autocomplete && $input.autocomplete('instance');
          if (autocomplete && autocomplete.menu) {
            autocomplete.menu.element.addClass('mukurtu-geocoder-autocomplete-menu');

            // jQuery UI appends the dropdown to <body> by default (no
            // ancestor here has a .ui-front/dialog class), so it positions
            // itself relative to the browser window instead of the map.
            // Re-parent it into this control so it stays inside the map's
            // own (overflow: hidden) container.
            $input.autocomplete('option', 'appendTo', container);

            // Cap the dropdown at the map's width rather than the window's,
            // so it can't overflow the map's left edge on a narrow screen
            // (see .mukurtu-geocoder-autocomplete-menu in the CSS).
            $input.on('autocompleteopen', function () {
              const map = container.closest('.leaflet-container');
              if (map) {
                autocomplete.menu.element[0].style.setProperty('--mukurtu-geocoder-menu-limit', 'calc(' + map.clientWidth + 'px - 2em)');
              }
            });

            Drupal.mukurtuGeocoderCombobox(input, autocomplete);
            Drupal.mukurtuGeocoderMessages(autocomplete);

            // jQuery UI treats Down Arrow on the last result as "back to what
            // I typed": it clears the highlight and restores the search text.
            // With a single result that leaves nothing for Enter to choose.
            // Stay on the last result instead. Up Arrow from the first result
            // still returns to the input, as the combobox pattern expects.
            const move = autocomplete._move;
            autocomplete._move = function (direction, event) {
              if (/^next/.test(direction) && this.menu.active && this.menu.isLastItem() && this.menu.element.is(':visible')) {
                return;
              }
              return move.apply(this, arguments);
            };
          }

          // Choosing a result only moves the map; the contrib control
          // doesn't place a marker, so nothing visible happens to the field.
          // Tell screen reader users the map moved and what's still needed.
          $input.on('autocompleteselect', function (event, ui) {
            if (ui.item && ui.item.value) {
              Drupal.announce(Drupal.t('Map moved to @address. Add a marker to save this location.', {'@address': ui.item.value}));
            }
          });
        }
      });
    }
  };

  /**
   * Give the geocoder search the ARIA combobox pattern.
   *
   * jQuery UI autocomplete adds no roles or states: the input isn't exposed
   * as a combobox, the results list has no role, and the highlighted result
   * is only announced through jQuery UI's own live region. Mark them up as a
   * combobox and listbox, and point aria-activedescendant at the highlighted
   * result so screen readers announce it themselves.
   */
  Drupal.mukurtuGeocoderCombobox = function (input, autocomplete) {
    const $menu = autocomplete.menu.element;
    const menuId = input.id + '--results';
    $menu.attr({id: menuId, role: 'listbox'});
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', menuId);

    // jQuery UI rebuilds the list for every search. With autoFocus it also
    // highlights the first result before firing "open", so both handlers
    // below make sure the new results are tagged.
    const tagOptions = function () {
      $menu.children('li:not([role])').each(function () {
        this.setAttribute('role', 'option');
        this.setAttribute('aria-selected', 'false');
        this.id = menuId + '-' + $(this).index();
      });
    };

    $(input).on('autocompleteopen', function () {
      tagOptions();
      input.setAttribute('aria-expanded', 'true');
    });
    $(input).on('autocompleteclose', function () {
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    });

    // Bound after jQuery UI's own menufocus/menublur handlers, so these run
    // after them in the same dispatch.
    $menu.on('menufocus', function (event, ui) {
      tagOptions();
      $menu.children('[aria-selected="true"]').attr('aria-selected', 'false');
      ui.item.attr('aria-selected', 'true');
      input.setAttribute('aria-activedescendant', ui.item.attr('id'));
      // jQuery UI has just queued the same result text for its live region.
      // aria-activedescendant already announces it, so cancel the repeat.
      clearTimeout(autocomplete.liveRegionTimer);
    });
    $menu.on('menublur', function () {
      $menu.children('[aria-selected="true"]').attr('aria-selected', 'false');
      input.removeAttribute('aria-activedescendant');
    });
  };

  // Set when the most recent geocoder request failed, so the "no results"
  // announcement can say the search is unavailable instead. Requests are
  // numbered so a slow reply to an older search can't overwrite the newest.
  let mukurtuGeocoderFailed = false;
  let mukurtuGeocoderRequest = 0;

  /**
   * Make the geocoder search's live region announcements translatable.
   *
   * jQuery UI's result count and "no results" messages are hardcoded in
   * English. The contrib control also turns a failed request into an empty
   * result list, so people heard "No search results." when the search
   * service was down or rate-limiting us, and couldn't tell the difference.
   */
  Drupal.mukurtuGeocoderMessages = function (autocomplete) {
    const geocode = Drupal.Leaflet && Drupal.Leaflet.prototype.geocode;
    if (geocode && !geocode.mukurtuWrapped) {
      Drupal.Leaflet.prototype.geocode = function () {
        const request = ++mukurtuGeocoderRequest;
        return geocode.apply(this, arguments)
          .done(function () {
            if (request === mukurtuGeocoderRequest) {
              mukurtuGeocoderFailed = false;
            }
          })
          .fail(function () {
            if (request === mukurtuGeocoderRequest) {
              mukurtuGeocoderFailed = true;
            }
          });
      };
      Drupal.Leaflet.prototype.geocode.mukurtuWrapped = true;
    }

    autocomplete.option('messages', {
      // A getter, because jQuery UI reads this each time a search returns.
      get noResults() {
        return mukurtuGeocoderFailed ?
          Drupal.t("Address search isn't available right now. Try again later, or add a marker on the map.") :
          Drupal.t('No search results.');
      },
      results: function (amount) {
        return Drupal.formatPlural(amount, '1 result is available, use up and down arrow keys to navigate.', '@count results are available, use up and down arrow keys to navigate.');
      }
    });
  };

})(jQuery, Drupal, once);

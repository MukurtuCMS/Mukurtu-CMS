**# Agents.md**

## Project Context

This is the **Drupal Leaflet contributed module** (`drupal/leaflet`) — installable via Composer (`composer require drupal/leaflet`). It integrates the Leaflet JS mapping library into Drupal as a field formatter, field widget, and Views style plugin.

- **Drupal.org project**: https://www.drupal.org/project/leaflet
- **Main dependencies**: `geofield` module (required), `geocoder` (optional), `token` (optional)

## Architecture Overview

### PHP Layer

**Central service** — `LeafletService` (`src/LeafletService.php`):
- Entry point for all programmatic map rendering: `\Drupal::service('leaflet.service')->leafletRenderMap($map, $features, $height)`
- Converts WKT geofield data to Leaflet feature arrays via `leafletProcessGeofield()` / `leafletProcessGeometry()`
- Conditionally attaches libraries based on map configuration
- Uses a dedicated cache bin (`cache.leaflet`) for map info and icon sizes

**Shared settings trait** — `LeafletSettingsElementsTrait` (`src/LeafletSettingsElementsTrait.php`):
- Centralises all form settings elements (map selection, popup, tooltip, controls) shared across the formatter, widget, and views style plugin
- Default settings for every map component live here

**Field plugins:**
- `LeafletDefaultFormatter` — read-only map from a geofield value; supports tokens, custom icons, path styles, popups/tooltips
- `LeafletDefaultWidget` — interactive map editor using Leaflet-Geoman; stores geometry back as GeoJSON → WKT

**Submodule `leaflet_views`** (`modules/leaflet_views/`):
- `LeafletMap` (views style) — renders an entire Views result set as a map
- `LeafletMarker` (views row) — converts a single result row into a map feature
- `LeafletAttachment` (views display) — attaches markers from one view onto another view's map
- `LeafletAjaxPopupController` — AJAX endpoint for lazy-loaded popup content

**Submodule `leaflet_markercluster`** (`modules/leaflet_markercluster/`):
- Wraps Leaflet.markercluster; integrates with both the formatter and views style plugin

### Hook & Extension Points (leaflet.module / leaflet.api.php)

| Hook | Purpose |
|------|---------|
| `hook_leaflet_map_info()` | Define named maps (tile layers, default settings) |
| `hook_leaflet_map_info_alter()` | Modify any map definition |
| `hook_theme()` | Declares `leaflet_map` theme; template vars: `map_id`, `height`, `map` |

JS events dispatched on the map container element:
- `leafletMapInit` — fired once the map is fully initialised (passes `map`, `lMap`, `mapid`, `markers`)
- `leaflet.map` — legacy alias
- `leaflet.feature` — fired per feature after it is added (passes `lFeature`, `feature`, `map`)
- `leaflet.features` — fired after all features are added

### JavaScript Layer

**`js/leaflet.drupal.js`** — core JS (~1 133 lines):
- `Drupal.behaviors.leaflet` — attaches once per `#mapid` container; uses `IntersectionObserver` for lazy init
- `Drupal.Leaflet` class — constructed with `(map_container, mapid, map_definition)`:
  - `initialise(mapid)` — creates `L.Map`, adds tile layers, sets initial view
  - `add_features(features, initial)` → `create_feature(feature)` → `create_geometry(feature)` — feature factory chain
  - `create_point / create_linestring / create_polygon / create_multipolygon` — geometry-specific constructors
  - `create_layer(layer, key)` — supports `L.tileLayer`, `L.tileLayer.wms`, and `L.maplibreGL`
  - `create_icon / create_divicon` — icon factories
  - `feature_bind_popup / feature_bind_tooltip` — content binding with AJAX lazy-load support
  - `fitBounds()` — auto-pans to show all features

**`js/leaflet.widget.js`** — edit widget:
- `Drupal.Leaflet_Widget` class initialises Leaflet-Geoman (`L.PM`); listens for `pm:create / pm:update / pm:remove` to serialise drawn items back to a GeoJSON hidden input

**`js/leaflet.geojson_overlays.js`** — async GeoJSON overlay loader for the widget

**`js/leaflet.geocoder.js`** — attaches an address-search control backed by the Drupal Geocoder API (`Drupal.Leaflet.prototype.map_geocoder_control`)

### Bundled Third-Party JS Libraries (in `js/`)

| Library | Purpose |
|---------|---------|
| `leaflet/dist/leaflet.js` | Core Leaflet |
| `maplibre-gl-js-*/` | Vector tile rendering |
| `leaflet-maplibre-gl-*/` | MapLibre ↔ Leaflet adapter |
| `leaflet-geoman-free/` | Geometry drawing/editing |
| `Leaflet.GestureHandling-*/` | Mobile gesture control |
| `Leaflet.ResetView/` | Reset-view button |
| `Leaflet.fullscreen-gh-pages/` | Fullscreen button |
| `leaflet-locatecontrol-gh-pages/` | User-location button |

Libraries are declared in `leaflet.libraries.yml` and attached conditionally in `LeafletService::leafletRenderMap()`.

## Data Flow

**Display path**: Geofield WKT → `LeafletService::leafletProcessGeofield()` → PHP feature array → drupalSettings → `Drupal.Leaflet::create_geometry()` → `L.Map`

**Edit path**: `Drupal.Leaflet_Widget` init → Geoman toolbar → user draws → `pm:*` events → `update_text()` → GeoJSON string in hidden input → form submit → geofield WKT

**Views path**: Query result rows → `LeafletMarker` row plugin → feature array → `LeafletMap` style plugin → `LeafletService::leafletRenderMap()`

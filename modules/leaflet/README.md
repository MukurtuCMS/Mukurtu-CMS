# Leaflet module

Advanced Drupal integration with the [Leaflet JS](https://leafletjs.com) mapping library.

> A Modern, Lightweight Open-Source JavaScript Library for Interactive Web Mapping.

[Drupal.org project page](https://www.drupal.org/project/leaflet) | [Community documentation](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/leaflet/)

---

## Table of Contents

- [Featured options and functionalities](#featured-options-and-functionalities)
- [Requirements](#requirements)
- [Installation](#installation)
- [Submodules](#submodules)
- [Field Formatter](#field-formatter)
- [Field Widget](#field-widget)
- [Map Definitions](#map-definitions)
- [Programmatic API](#programmatic-api)
- [Feature Types](#feature-types)
- [Icon Options](#icon-options)
- [Path / Geometry Styling](#path--geometry-styling)
- [Map Controls](#map-controls)
- [Hooks Reference](#hooks-reference)
- [JavaScript Events API](#javascript-events-api)
- [Bundled Libraries](#bundled-libraries)
- [Token Support](#token-support)
- [Authors / Credits](#authors--credits)

---

## Featured options and functionalities

- Easy-to-use API for extended Leaflet map definition and customisation;
- Field widget with [Leaflet-Geoman](https://github.com/geoman-io/leaflet-geoman)
  integration for creating and editing Points and Geometries (LineString, Polygon);
- GeoJSON overlays (external and internal sources) on the widget map as visual
  snapping references for precise drawing;
- Popups and Tooltips on map features;
- Multi-layer base map control and overlay layers control via Drupal Views grouping;
- Dynamic marker icons and path/geometry styling with Token and replacement-pattern support;
- Marker clustering via [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster);
- Bundled map controls: gesture handling ([GestureHandling](https://github.com/elmarquis/Leaflet.GestureHandling)),
  reset view ([ResetView](https://github.com/drustack/Leaflet.ResetView)),
  fullscreen ([FullScreen](https://github.com/brunob/leaflet.fullscreen)),
  and user location ([Locate](https://github.com/domoritz/leaflet-locatecontrol));
- Feature additional properties for advanced and dynamic customisation of map
  and feature rendering logic;
- Address search geocoding with autocomplete (requires Geocoder module);
- Multiple Leaflet maps (formatters, Views, and widgets) on the same page;
- Drupal hooks for altering map definitions, features, and rendering.

---

## Requirements

- [Geofield](https://www.drupal.org/project/geofield) module (required — provides the geofield field type and GeoPHP integration)
- [Geocoder](https://www.drupal.org/project/geocoder) module (optional — enables address-search geocoding control)
- [Token](https://www.drupal.org/project/token) module (optional — enables token replacement in popup, tooltip, icon, and path settings)

---

## Installation

Download and enable via Composer:

```bash
composer require drupal/leaflet
drush en leaflet
```

This installs the core **Leaflet** module, which provides:

- **Leaflet Map Geofield Formatter** — displays a geofield as an interactive map.
- **Leaflet Map Geofield Widget** — edits a geofield on an interactive map with drawing tools.

---

## Submodules

### Leaflet Views (`leaflet_views`)

Adds a **Leaflet Map** Views style plugin so any View with a geofield can be rendered as a map. Enable separately:

```bash
drush en leaflet_views
```

In any View, add a geofield to the Fields list and select **Leaflet Map** as the Format style. Each row becomes a map marker; groups become overlay layers (toggle-able via the layer control).

The submodule also provides:

- **LeafletAttachment** display plugin — attaches the markers of one View onto the map of another.
- **AJAX popup controller** — lazy-loads popup content on marker click without a full page reload.

### Leaflet Markercluster (`leaflet_markercluster`)

Wraps the [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) plugin. Enable separately:

```bash
drush en leaflet_markercluster
```

Once enabled, a **Marker Cluster** section appears in the formatter and Views style settings. Configure clustering options as a JSON string (see [Markercluster options](https://github.com/Leaflet/Leaflet.markercluster#options)).

---

## Field Formatter

The **Leaflet Map Geofield Formatter** renders a geofield value as a read-only interactive map. Configure it under **Manage display** for any content type that has a geofield.

---

## Field Widget

The **Leaflet Map Geofield Widget** renders an interactive editing map using [Leaflet-Geoman](https://geoman.io/) for drawing. Configure it under **Manage form display**.

### GeoJSON Overlays

Overlay existing geofield data onto the widget map as a visual snapping reference. Under **GeoJSON Overlays**, select one or more geofield fields from the same form. The overlays are styled independently (configurable as a JSON path-options string) and can optionally auto-zoom the map to fit the overlay extent.

---

## Map Definitions

A **map definition** is a named PHP array that describes the tile layers, default settings, and optional icon/path overrides for a Leaflet map. The module ships two definitions out of the box:

- **OSM Mapnik** — single OpenStreetMap tile layer.
- **Multilayers** — multiple base layers (Stadia, Google, OSM, OpenTopoMap, MapTiler) with an optional OpenRailwayMap overlay.

Retrieve all available map definitions programmatically:

```php
$maps = leaflet_map_get_info();         // all maps
$map  = leaflet_map_get_info('OSM Mapnik'); // single map
```

### Defining a custom map — `hook_leaflet_map_info()`

```php
function mymodule_leaflet_map_info(): array {
  return [
    'My Custom Map' => [
      'label'       => 'My Custom Map',
      'description' => t('Custom tile layer with an overlay.'),
      'settings'    => leaflet_map_info_default_settings() + [
        'zoom'    => 10,
        'minZoom' => 5,
        'maxZoom' => 18,
      ],
      'layers' => [
        // Standard raster tile layer.
        'OpenStreetMap' => [
          'urlTemplate' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          'options' => [
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'maxZoom' => 19,
          ],
        ],
        // Optional overlay layer (shown in layer control, initially hidden).
        'OpenRailwayMap' => [
          'layer_type'   => 'overlay',
          'layer_hidden' => TRUE,
          'urlTemplate'  => 'https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png',
          'options' => [
            'maxZoom'     => 19,
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> | <a href="https://www.openrailwaymap.org">OpenRailwayMap</a>',
          ],
        ],
        // Vector tile layer rendered via MapLibre GL JS.
        'Stadia Dark (Vector)' => [
          'type'        => 'vector',
          'urlTemplate' => 'https://tiles.stadiamaps.com/styles/alidade_smooth_dark.json',
          'options' => [
            'attribution' => '&copy; Stadia Maps, &copy; OpenMapTiles &copy; OpenStreetMap contributors',
            'pitch'       => '0',
            'bearing'     => '0',
          ],
        ],
        // Google Maps raster tile layer.
        'Google Roads' => [
          'type'        => 'google',
          'urlTemplate' => 'https://mt{s}.googleapis.com/vt?x={x}&y={y}&z={z}',
          'options' => [
            'attribution' => 'Map data &copy; Google',
            'subdomains'  => [0, 1, 2, 3],
          ],
        ],
      ],
      // Optional default icon for all features on this map.
      'icon' => [
        'iconUrl'     => '/sites/default/files/marker.png',
        'iconSize'    => ['x' => 25, 'y' => 41],
        'iconAnchor'  => ['x' => 12, 'y' => 41],
        'popupAnchor' => ['x' => 0, 'y' => -41],
        'shadowUrl'   => '/sites/default/files/marker-shadow.png',
        'shadowSize'  => ['x' => 41, 'y' => 41],
      ],
      // Optional default path style for geometries.
      'path' => [
        'color'       => '#3388ff',
        'opacity'     => '1.0',
        'weight'      => 3,
        'fill'        => TRUE,
        'fillColor'   => '#3388ff',
        'fillOpacity' => '0.2',
      ],
      'plugins' => [],
    ],
  ];
}
```

### Default map settings (`leaflet_map_info_default_settings()`)

```php
[
  'dragging'             => TRUE,
  'touchZoom'            => TRUE,
  'scrollWheelZoom'      => TRUE,
  'doubleClickZoom'      => TRUE,
  'zoomControl'          => TRUE,
  'zoomControlPosition'  => 'topleft',   // 'topleft'|'topright'|'bottomleft'|'bottomright'
  'attributionControl'   => TRUE,
  'trackResize'          => TRUE,
  'fadeAnimation'        => TRUE,
  'zoomAnimation'        => TRUE,
  'closePopupOnClick'    => TRUE,
  'minZoom'              => 2,
  'maxZoom'              => 20,
  'zoom'                 => 15,
  'layerControl'         => TRUE,        // Show layer switcher when multiple layers exist.
  'layerControlOptions'  => ['position' => 'topright'],
]
```

---

## Programmatic API

The central service is `leaflet.service` (`\Drupal\leaflet\LeafletService`).

### Rendering a map

```php
/** @var \Drupal\leaflet\LeafletService $leaflet */
$leaflet = \Drupal::service('leaflet.service');

$map      = leaflet_map_get_info('OSM Mapnik');
$features = $leaflet->leafletProcessGeofield($geofield_value);
$height   = '500px';

$render = $leaflet->leafletRenderMap($map, $features, $height);
```

`leafletRenderMap()` returns a Drupal render array (`#theme: leaflet_map`) with all required libraries and `drupalSettings` already attached. It automatically includes optional libraries (fullscreen, locate, geocoder, gesture handling, markercluster) based on the map configuration.

### Converting geofield data to features

```php
// $items can be a single WKT/JSON string or an array of them.
$features = $leaflet->leafletProcessGeofield($items);
```

Supports all geometry types: Point, LineString, Polygon, MultiPoint, MultiLineString, MultiPolygon, GeometryCollection.

### Building features manually

Instead of converting a geofield, you can build the feature array directly and pass it to `leafletRenderMap()`:

```php
$features = [];

// Point.
$features[] = [
  'type'    => 'point',
  'lat'     => 40.7128,
  'lon'     => -74.0060,
  'popup'   => '<strong>New York City</strong>',
  'tooltip' => 'NYC',
  'icon'    => ['iconUrl' => '/path/to/icon.png', 'iconSize' => ['x' => 25, 'y' => 41]],
];

// LineString.
$features[] = [
  'type'   => 'linestring',
  'points' => [
    ['lat' => 40.7128, 'lon' => -74.0060],
    ['lat' => 34.0522, 'lon' => -118.2437],
  ],
  'path' => ['color' => 'red', 'weight' => 4, 'opacity' => 0.8],
];

// Polygon.
$features[] = [
  'type'   => 'polygon',
  'points' => [[
    ['lat' => 40.70, 'lon' => -74.01],
    ['lat' => 40.72, 'lon' => -74.01],
    ['lat' => 40.72, 'lon' => -73.99],
    ['lat' => 40.70, 'lon' => -73.99],
  ]],
  'path' => ['color' => '#3388ff', 'fillOpacity' => 0.3],
];

// Raw GeoJSON object (with optional JS callbacks).
$features[] = [
  'type'   => 'json',
  'json'   => $geojson_array,    // Decoded GeoJSON FeatureCollection or Feature.
  'events' => [
    'click' => 'Drupal.myModule.handleClick',  // JS callback name.
  ],
  'options' => [
    'pointToLayer'       => 'function(geoJsonPoint, latlng) { return L.circleMarker(latlng); }',
    'onEachFeature'      => 'function(feature, layer) {}',
    'markersInheritOptions' => FALSE,
  ],
];
```

### Grouping features into overlay layers

Wrap features in a `group` to make them toggle-able in the layer control:

```php
$features[] = [
  'group'          => TRUE,
  'group_label'    => 'Restaurants',
  'features'       => $restaurant_features,
  'layer_hidden'   => FALSE,
];
```

### Feature additional properties

Add arbitrary key/value pairs to a feature for use in JS event handlers or custom rendering:

```php
$features[] = [
  'type'  => 'point',
  'lat'   => 40.7128,
  'lon'   => -74.0060,
  'feature_properties' => [
    'category' => 'museum',
    'node_id'  => 42,
  ],
];
```

These are available in JS as `feature.feature_properties`.

---

## Feature Types

| PHP `type` key | Leaflet object | Required keys |
|----------------|---------------|---------------|
| `point` | `L.Marker` | `lat`, `lon` |
| `linestring` | `L.Polyline` | `points` (array of `{lat, lon}`) |
| `polygon` | `L.Polygon` | `points` (array of rings, each an array of `{lat, lon}`) |
| `multipolygon` | Multiple `L.Polygon` | `points` |
| `multipolyline` | Multiple `L.Polyline` | `component` (array of `{points}`) |
| `geometrycollection` | Mixed | `component` (array of typed sub-features) |
| `json` | `L.GeoJSON` | `json` (decoded GeoJSON array) |

---

## Icon Options

All icon settings support [Token replacement](#token-support).

### Standard icon (`iconType: 'marker'`)

```php
'icon' => [
  'iconType'    => 'marker',
  'iconUrl'     => '/sites/default/files/pin.png',  // Required.
  'shadowUrl'   => '/sites/default/files/pin-shadow.png',
  'iconSize'    => ['x' => 25, 'y' => 41],
  'iconAnchor'  => ['x' => 12, 'y' => 41],
  'shadowSize'  => ['x' => 41, 'y' => 41],
  'shadowAnchor'=> ['x' => 12, 'y' => 41],
  'popupAnchor' => ['x' => 0,  'y' => -41],
  'className'   => 'my-marker-class',
]
```

If `iconSize` is omitted, the module detects image dimensions automatically (with caching).

### HTML / DivIcon (`iconType: 'html'`)

```php
'icon' => [
  'iconType'   => 'html',
  'html'       => '<div class="custom-pin">[node:title]</div>',
  'html_class' => 'leaflet-map-divicon',
  'iconSize'   => ['x' => 40, 'y' => 40],
  'iconAnchor' => ['x' => 20, 'y' => 40],
]
```

### Circle marker (`iconType: 'circle_marker'`)

Rendered entirely in SVG — no image required:

```php
'icon' => [
  'iconType'              => 'circle_marker',
  'circle_marker_options' => '{"radius":8,"color":"#e74c3c","fillColor":"#e74c3c","fillOpacity":0.9}',
]
```

---

## Path / Geometry Styling

A JSON string (or PHP array in map definitions) controlling how LineStrings and Polygons are rendered. All [Leaflet Path options](https://leafletjs.com/reference.html#path) are supported.

```json
{
  "color":       "#3388ff",
  "opacity":     "1.0",
  "stroke":      true,
  "weight":      3,
  "fill":        true,
  "fillColor":   "#3388ff",
  "fillOpacity": "0.2",
  "radius":      6
}
```

Set `"fillColor": "*"` to inherit the stroke `color`.

---

## Map Controls

All controls are opt-in. Configure each as a JSON options string. Control positions are `topleft`, `topright`, `bottomleft`, or `bottomright`.

| Control | Library | Default options |
|---------|---------|----------------|
| **Reset view** | `leaflet/leaflet.reset_map_view` | `{"position":"topleft","title":"Reset View"}` |
| **Fullscreen** | `leaflet/leaflet.fullscreen` | `{"position":"topleft","pseudoFullscreen":false}` |
| **Locate (GPS)** | `leaflet/leaflet.locatecontrol` | `{"position":"topright","setView":"untilPanOrZoom","returnToPrevBounds":true,"keepCurrentZoomLevel":true}` |
| **Scale bar** | Leaflet built-in | `{"position":"bottomright","maxWidth":100,"metric":true,"imperial":false}` |
| **Gesture handling** | `leaflet/leaflet.gesture_handling` | _(no options)_ |
| **Geocoder** | `leaflet/leaflet.geocoder` | Requires Geocoder module; configure providers in Geocoder settings. |

The Locate control also supports `automatic: true` to trigger geolocation on map load.

---

## Hooks Reference

### `hook_leaflet_map_info(): array`

Define one or more named map definitions. See [Map Definitions](#map-definitions) for the full structure.

### `hook_leaflet_map_info_alter(array &$map_info): void`

Alter any map definition after all modules have contributed theirs.

```php
function mymodule_leaflet_map_info_alter(array &$map_info): void {
  // Override the default marker icon for every map.
  foreach ($map_info as &$map) {
    $map['icon']['iconUrl'] = '/themes/mytheme/img/pin.png';
  }
}
```

### `hook_leaflet_formatter_feature_alter(array &$feature, FieldItemInterface $item, ContentEntityInterface $entity): void`

Alter a single feature array produced by the Leaflet Formatter before it is passed to `leafletRenderMap()`. Use this to override the popup, change the icon, or add `feature_properties` based on entity data.

```php
function mymodule_leaflet_formatter_feature_alter(
  array &$feature,
  FieldItemInterface $item,
  ContentEntityInterface $entity
): void {
  // Colour-code markers by a taxonomy term field.
  $term = $entity->get('field_category')->entity;
  if ($term) {
    $feature['icon']['iconType'] = 'circle_marker';
    $feature['icon']['circle_marker_options'] = json_encode([
      'radius'      => 8,
      'color'       => $term->get('field_color')->value,
      'fillOpacity' => 0.9,
    ]);
  }
}
```

### `hook_leaflet_default_map_formatter_alter(array &$map_settings, FieldItemListInterface &$items): void`

Alter the full map settings array used by the Formatter (map tiles, zoom, controls, etc.) before the map is rendered.

```php
function mymodule_leaflet_default_map_formatter_alter(
  array &$map_settings,
  FieldItemListInterface &$items
): void {
  // Force a specific zoom level when rendering on the full node view.
  $map_settings['zoom'] = 12;
}
```

### `hook_leaflet_default_widget_alter(array &$map_settings, GeofieldBaseWidget $leafletDefaultWidget): void`

Alter the Widget map settings (JavaScript `drupalSettings`) before they are passed to the browser.

```php
function mymodule_leaflet_default_widget_alter(
  array &$map_settings,
  GeofieldBaseWidget $leafletDefaultWidget
): void {
  // Disable scroll-wheel zoom in the widget.
  $map_settings['map']['scrollWheelZoom'] = FALSE;
}
```

---

## JavaScript Events API

All events are dispatched on the map container DOM element and bubble up the DOM. Use them to integrate custom JavaScript without patching the module.

### `leafletMapInit`

Fired once after the Leaflet map is fully initialised and all features are added.

```javascript
document.addEventListener('leafletMapInit', function (e) {
  const { map, lMap, mapid, markers } = e.originalEvent.detail;
  // map        — the Drupal.Leaflet instance.
  // lMap       — the native L.Map instance.
  // mapid      — the map container ID string.
  // markers    — object mapping entity IDs to L.Marker instances.

  // Example: add a custom control.
  L.control.scale().addTo(lMap);
});
```

### `leaflet.map`

Legacy alias for `leafletMapInit`. Prefer `leafletMapInit` in new code.

### `leaflet.feature`

Fired each time a single feature is added to the map.

```javascript
document.addEventListener('leaflet.feature', function (e) {
  const { lFeature, feature } = e.originalEvent.detail;
  // lFeature — the Leaflet layer object (L.Marker, L.Polygon, etc.)
  // feature  — the raw PHP feature array passed through drupalSettings.
});
```

### `leaflet.features`

Fired once after all features have been added (before `fitBounds`).

---

## Bundled Libraries

All third-party JS/CSS libraries are bundled in the `js/` directory and declared in `leaflet.libraries.yml`. They are attached automatically when needed.

| Drupal library key | Purpose |
|--------------------|---------|
| `leaflet/leaflet` | Core Leaflet JS + CSS |
| `leaflet/leaflet-drupal` | Drupal integration behaviour (`leaflet.drupal.js`) |
| `leaflet/maplibre-gl-js` | Vector tile rendering engine |
| `leaflet/maplibre-gl-leaflet` | Bridges MapLibre GL into a Leaflet layer |
| `leaflet/leaflet-geoman` | Geometry drawing/editing toolbar |
| `leaflet/leaflet-widget` | Drupal widget behaviour (`leaflet.widget.js`) |
| `leaflet/leaflet-geojson-overlay` | GeoJSON overlay loader for the widget |
| `leaflet/leaflet.fullscreen` | Fullscreen control |
| `leaflet/leaflet.reset_map_view` | Reset-view button |
| `leaflet/leaflet.gesture_handling` | Gesture/scroll handling (mobile-friendly) |
| `leaflet/leaflet.locatecontrol` | GPS locate button |
| `leaflet/leaflet.geocoder` | Custom geocoder control backed by Drupal Geocoder API |
| `leaflet_markercluster/leaflet-markercluster` | Marker clustering |

---

## Token Support

When the [Token](https://www.drupal.org/project/token) module is enabled, the following settings accept standard Drupal token syntax (e.g. `[node:title]`, `[node:field_color]`):

- Popup content, tooltip content
- Icon URL (`iconUrl`, `shadowUrl`)
- Icon / shadow / popup anchor dimensions
- DivIcon HTML content (`html`)
- Path / circle marker options (JSON string)
- Feature weight / zIndex
- Feature additional properties values

A **Browse available tokens** widget is displayed in the relevant settings fields when Token is present.

---

## Authors / Maintainers

From Drupal 8 to today:
- **Italo Mairo** — [itamair](https://www.drupal.org/u/itamair) — main maintainer

Drupal 7:
- **Lev Tsypin** — [levelos](https://www.drupal.org/u/levelos) — original creator
- **Peter Vanhee** — [pvhee](https://www.drupal.org/u/pvhee)
- **Rik de Boer** — [RdeBoer](https://www.drupal.org/u/rdeboer)

And credits to the wider Drupal community

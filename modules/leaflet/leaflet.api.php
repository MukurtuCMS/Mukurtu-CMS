<?php

/**
 * @file
 * API documentation for Leaflet module.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldBaseWidget;

/**
 * Returns leaflet map info default settings.
 */
function leaflet_map_info_default_settings() {
  return [
    'dragging' => TRUE,
    'touchZoom' => TRUE,
    'scrollWheelZoom' => TRUE,
    'doubleClickZoom' => TRUE,
    'zoomControl' => TRUE,
    'zoomControlPosition' => 'topleft',
    'attributionControl' => TRUE,
    'trackResize' => TRUE,
    'fadeAnimation' => TRUE,
    'zoomAnimation' => TRUE,
    'closePopupOnClick' => TRUE,
    'minZoom' => 2,
    'maxZoom' => 20,
    'zoom' => 15,
    // Specific of the Drupal Leaflet module
    // Enables Layer Control in case of multiple layers, and add options.
    'layerControl' => TRUE,
    'layerControlOptions' => [
      'position' => 'topright',
    ],
  ];
}

/**
 * Define map definitions to be used when rendering a map.
 *
 * The leaflet_map_get_info() will grab every defined map, and the returned
 * associative array is then passed to
 * \Drupal::service('leaflet.service')->leafletRenderMap(), along with a
 * collection of features.
 *
 * The settings array maps to the settings available to the leaflet map object,
 * see http://leafletjs.com/reference.html#map-property.
 *
 * Layers are the available base layers for the map and, if you enable the
 * layer control, can be toggled on the map.
 *
 * @return array
 *   The definitions array.
 */
function hook_leaflet_map_info() {
  return [
    'OSM Mapnik' => [
      'label' => 'OSM Mapnik',
      'description' => t('Leaflet default map.'),
      'settings' => leaflet_map_info_default_settings(),
      'layers' => [
        'OpenStreetMap' => [
          'urlTemplate' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          'options' => [
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a> contributors',
          ],
        ],
      ],
      // Uncomment the lines below to use a custom path style for geometries.
      /*'path' => [
        "color" => "black",
        "opacity" => "0.8",
        "stroke" => 2,
        "fill" => TRUE,
        "fillColor" => "blue",
        "fillOpacity" => "0.1",
      ],*/
      // Uncomment the lines below to use a custom icon.
      /*'icon' => array(
        'iconUrl'       => '/sites/default/files/icon.png',
        'iconSize'      => array('x' => '20', 'y' => '40'),
        'iconAnchor'    => array('x' => '20', 'y' => '40'),
        'popupAnchor'   => array('x' => '-8', 'y' => '-32'),
        'shadowUrl'     => '/sites/default/files/icon-shadow.png',
        'shadowSize'    => array('x' => '25', 'y' => '27'),
        'shadowAnchor'  => array('x' => '0', 'y' => '27'),
      ),*/
      // Enable and configure plugins in the plugins array.
      'plugins' => [],
    ],
    'multilayers' => [
      'label' => 'Multilayers',
      'description' => t('Multilayers'),
      'settings' => leaflet_map_info_default_settings(),
      'layers' => [
        'Stadia Alidade Smooth' => [
          'urlTemplate' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png',
          'options' => [
            "maxZoom" => 20,
            'attribution' => '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>, &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
          ],
        ],
        'Google Roads' => [
          'type' => 'google',
          'urlTemplate' => 'https://mt{s}.googleapis.com/vt?x={x}&y={y}&z={z}',
          'options' => [
            'attribution' => 'Map data &copy; <a href="https://googlemaps.com">Google</a>',
            'detectRetina' => FALSE,
            'subdomains' => [0, 1, 2, 3],
          ],
        ],
        'OpenStreetMap' => [
          'urlTemplate' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          'options' => [
            "maxZoom" => 19,
            "attribution" => "&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
          ],
        ],
        'Stamen TonerLite' => [
          'urlTemplate' => 'https://tiles.stadiamaps.com/tiles/stamen_toner_lite/{z}/{x}/{y}{r}.{ext}',
          'options' => [
            "subdomains" => "abcd",
            "minZoom" => 0,
            "maxZoom" => 20,
            "ext" => "png",
            "attribution" => "&copy; <a href='https://www.stadiamaps.com/'>Stadia Maps</a> &copy; <a href='https://www.stamen.com/'>Stamen Design</a> &copy; <a href='https://openmaptiles.org/'>OpenMapTiles</a> &copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
          ],
        ],
        'OpenTopoMap' => [
          'urlTemplate' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
          'options' => [
            "minZoom" => 0,
            "maxZoom" => 18,
            "attribution" => 'Kartendaten: © <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende, SRTM | Kartendarstellung: © <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
          ],
        ],
        // A Vector Tile definition usable by MapLibre GL Leaflet & Js.
        // @see https://docs.stadiamaps.com/tutorials/vector-tiles-with-leaflet/
        'Stadia Alidade Smooth Dark Vector' => [
          // Set vector type for rendering with Vector.
          'type' => 'vector',
          'urlTemplate' => 'https://tiles.stadiamaps.com/styles/alidade_smooth_dark.json',
          'options' => [
            'attribution' => '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>, &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
            // Supports Pitch in degrees (optional).
            'pitch' => '0',
            // Supports Bearing in degrees (optional).
            'bearing' => '0',
          ],
        ],
        // A Map Tiler (Aquarelle style) integration example.
        // @see https://cloud.maptiler.com/maps/aquarelle/
        'Map Tiler Aquarelle' => [
          'urlTemplate' => 'https://api.maptiler.com/maps/aquarelle/{z}/{x}/{y}.png?key=OIQHXCpngQEP6dfvC5nq',
          'options' => [
            "tileSize" => 512,
            "zoomOffset" => -1,
            "minZoom" => 0,
            "maxZoom" => 22,
            'attribution' => "\u003ca href=\"https://www.maptiler.com/copyright/\" target=\"_blank\"\u003e\u0026copy; MapTiler\u003c/a\u003e \u003ca href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\"\u003e\u0026copy; OpenStreetMap contributors\u003c/a\u003e",
            'crossOrigin' => TRUE,
          ],
        ],
        // A Map Tiler (Backdrop style) integration example.
        // @see https://cloud.maptiler.com/maps/backdrop/
        'Map Tiler Backdrop' => [
          'urlTemplate' => 'https://api.maptiler.com/maps/backdrop/{z}/{x}/{y}.png?key=OIQHXCpngQEP6dfvC5nq',
          'options' => [
            "tileSize" => 512,
            "zoomOffset" => -1,
            "minZoom" => 0,
            "maxZoom" => 22,
            'attribution' => "\u003ca href=\"https://www.maptiler.com/copyright/\" target=\"_blank\"\u003e\u0026copy; MapTiler\u003c/a\u003e \u003ca href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\"\u003e\u0026copy; OpenStreetMap contributors\u003c/a\u003e",
            'crossOrigin' => TRUE,
          ],
        ],
        // An example Overlay definition, in addition to previous Base Layers.
        // (@see @3050934: simultaneous multiple layers feature)
        'OpenRailwayMap' => [
          // Set layer type as 'overlay'.
          'layer_type' => 'overlay',
          // Possibly set it also initially hidden.
          'layer_hidden' => TRUE,
          'urlTemplate' => 'https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png',
          'options' => [
            'maxZoom' => 19,
            'attribution' => 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | Map style: &copy; <a href="https://www.OpenRailwayMap.org">OpenRailwayMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
          ],
        ],
      ],
    ],
  ];
}

/**
 * Alters the map definitions defined by hook_leaflet_map_info().
 *
 * The settings array maps to the settings available to the leaflet map object,
 * http://leafletjs.com/reference.html#map-properties.
 *
 * @param array $map_info
 *   Map info array.
 */
function hook_leaflet_map_info_alter(array &$map_info) {
  // Set a custom iconUrl for the default map type.
  $map_info['OSM Mapnik']['icon']['iconUrl'] = '/sites/default/files/icon.png';
}

/**
 * Alter the Leaflet Map Default Widget settings.
 *
 * Allow other modules to add/alter the map js settings.
 *
 * @param array $map_settings
 *   The array of geofield map element settings.
 * @param \Drupal\geofield\Plugin\Field\FieldWidget\GeofieldBaseWidget $leafletDefaultWidget
 *   The Leaflet default Widget.
 * */
function hook_leaflet_default_widget_alter(array &$map_settings, GeofieldBaseWidget $leafletDefaultWidget) {
  // Make custom alterations to $map_settings, eventually using the $items
  // context.
}

/**
 * Adjust the array representing a leaflet formatter feature/marker.
 *
 * @param array $feature
 *   The leaflet feature. Available keys are:
 *   - type: Indicates the type of feature (usually one of these: point,
 *     polygon, linestring, multipolygon, multipolyline).
 *   - popup: This value is displayed in a popup after the user clicks on the
 *     feature.
 *   - Other possible keys include "lat", "lon", "points", "component",
 *     depending on feature type.
 * @param \Drupal\Core\Field\FieldItemInterface $item
 *   The Geofield Item.
 * @param \Drupal\Core\Entity\ContentEntityInterface $entity
 *   The Content Entity base of the formatter.
 */
function hook_leaflet_formatter_feature_alter(array &$feature, FieldItemInterface $item, ContentEntityInterface $entity) {
  // Make custom alterations to $feature, eventually using the $items
  // context.
}

/**
 * Alter the Leaflet Map Default Formatter settings.
 *
 * Allow other modules to add/alter the map js settings.
 *
 * @param array $map_settings
 *   The array of geofield map element settings.
 * @param \Drupal\Core\Field\FieldItemListInterface $items
 *   The field values to be rendered.
 * */
function hook_leaflet_default_map_formatter_alter(array &$map_settings, FieldItemListInterface &$items) {
  // Make custom alterations to $map_settings, eventually using the $items
  // context.
}

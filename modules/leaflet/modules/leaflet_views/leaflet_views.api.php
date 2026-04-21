<?php

/**
 * @file
 * Hook documentation for leaflet_views module.
 */

use Drupal\views\Plugin\views\ViewsPluginInterface;
use Drupal\views\ResultRow;

/**
 * Allow other modules to add/alter the $geofield_value.
 *
 * @param array $geofield_value
 *   The original Geofield Value (aways as array, also for single value).
 * @param array $map
 *   The map array definition.
 * @param array $leaflet_view_geofield_value_alter_context
 *   The leaflet_view_geofield_value_alter_context array.
 */
function hook_leaflet_map_view_geofield_value_alter(array &$geofield_value, array &$map, array $leaflet_view_geofield_value_alter_context): void {
  // Make custom alterations to $geofield_value.
}

/**
 * Adjust the array representing a leaflet view feature/marker.
 *
 * @param array $feature
 *   The leaflet feature. Available keys are:
 *   - type: Indicates the type of feature (usually one of these: point,
 *     polygon, linestring, multipolygon, multipolyline).
 *   - popup: This value is displayed in a popup after the user clicks on the
 *     feature.
 *   - label: Not used at the moment.
 *   - Other possible keys include "lat", "lon", "points", "component",
 *     depending on feature type.
 *     {@see \Drupal::service('leaflet.service')->leafletProcessGeofield()}
 *     for details.
 * @param \Drupal\views\ResultRow $row
 *   The views result row.
 * @param \Drupal\views\Plugin\views\ViewsPluginInterface $rowPlugin
 *   (optional) The row plugin used for rendering the feature.
 */
function hook_leaflet_views_feature_alter(array &$feature, ResultRow $row, ?ViewsPluginInterface $rowPlugin = NULL): void {
}

/**
 * Adjust the array representing a leaflet single features.
 *
 * @param array $features
 *   The leaflet single features definition.
 * @param \Drupal\views\Plugin\views\ViewsPluginInterface $view_style
 *   The Leaflet Map View Style.
 */
function hook_leaflet_views_features_alter(array &$features, ViewsPluginInterface &$view_style): void {
  // Make custom alterations to $features, eventually using the $view_style
  // context.
}

/**
 * Adjust the array representing a leaflet feature group.
 *
 * @param array $group
 *   The leaflet feature group. Available keys are:
 *   - group: Indicates whether the contained features should be rendered as a
 *   - group_label: The group label, e.g. used for the layer control widget.
 *   - disabled: The flag to set the Features Group Layer initially disabled.
 *   - features: List of features contained in this group.
 * @param \Drupal\views\Plugin\views\ViewsPluginInterface $view_style
 *   The Leaflet Map View Style.
 */
function hook_leaflet_views_features_group_alter(array &$group, ViewsPluginInterface &$view_style): void {
  // Make custom alterations to $group, eventually using the $view_style
  // context.
}

/**
 * Alter the Leaflet Map View Style settings.
 *
 * Allow other modules to add/alter the map js settings.
 *
 * @param array $map_settings
 *   The array of geofield map element settings.
 * @param \Drupal\views\Plugin\views\ViewsPluginInterface $view_style
 *   The Leaflet Map View Style.
 * */
function hook_leaflet_map_view_style_alter(array &$map_settings, ViewsPluginInterface &$view_style): void {
  // Make custom alterations to $map_settings, eventually using the $view_style
  // context.
}

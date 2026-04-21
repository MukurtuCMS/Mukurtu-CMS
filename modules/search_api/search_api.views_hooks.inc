<?php

/**
 * @file
 * Views hook implementations for the Search API module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\search_api\Hook\SearchApiViewsHooks;

/**
 * Implements hook_views_data().
 *
 * For each search index, we provide the following tables:
 * - One base table, with key "search_api_index_INDEX", which contains field,
 *   filter, argument and sort handlers for all indexed fields. (Field handlers,
 *   too, to allow things like click-sorting.)
 * - Tables for each datasource, by default with key
 *   "search_api_datasource_INDEX_DATASOURCE", with field and (where applicable)
 *   relationship handlers for each property of the datasource. Those will be
 *   joined to the index base table by default.
 *
 * Also, for each entity type encountered in any table, a table with
 * field/relationship handlers for all of that entity type's properties is
 * created. Those tables will use the key "search_api_entity_ENTITY".
 */
#[LegacyHook]
function search_api_views_data(): array {
  return \Drupal::getContainer()->get(SearchApiViewsHooks::class)->viewsData();
}

/**
 * Implements hook_views_plugins_argument_alter().
 */
#[LegacyHook]
function search_api_views_plugins_argument_alter(array &$plugins): void {
  \Drupal::getContainer()->get(SearchApiViewsHooks::class)->argumentPluginsAlter($plugins);
}

/**
 * Implements hook_views_plugins_cache_alter().
 */
#[LegacyHook]
function search_api_views_plugins_cache_alter(array &$plugins): void {
  \Drupal::getContainer()->get(SearchApiViewsHooks::class)->cachePluginsAlter($plugins);
}

/**
 * Implements hook_views_plugins_filter_alter().
 */
#[LegacyHook]
function search_api_views_plugins_filter_alter(array &$plugins): void {
  \Drupal::getContainer()->get(SearchApiViewsHooks::class)->filterPluginsAlter($plugins);
}

/**
 * Implements hook_views_plugins_row_alter().
 */
#[LegacyHook]
function search_api_views_plugins_row_alter(array &$plugins): void {
  \Drupal::getContainer()->get(SearchApiViewsHooks::class)->rowPluginsAlter($plugins);
}

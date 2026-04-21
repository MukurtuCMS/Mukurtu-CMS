<?php

/**
 * Implements hook_views_data_alter().
 *
 * @param array<string, mixed> $data
 *   The views data.
 */
function search_api_solr_log_views_data_alter(array &$data): void {
  $data['search_api_index_search_api_solr_log']['severity']['field']['id'] = 'search_api_solr_log_severity';
  $data['search_api_index_search_api_solr_log']['message']['field']['id'] = 'search_api_solr_log_message';
  $data['search_api_index_search_api_solr_log']['uid']['field']['id'] = 'search_api_solr_log_user';
}

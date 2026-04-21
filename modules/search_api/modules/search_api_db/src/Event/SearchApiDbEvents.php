<?php

namespace Drupal\search_api_db\Event;

/**
 * Defines events for the Database Search module.
 */
class SearchApiDbEvents {

  /**
   * The name of the event fired before executing a search query.
   *
   * This allows other modules to alter the DB query before a count query (or
   * facet queries, or other related queries) are constructed from it.
   *
   * @Event
   *
   * @see \Drupal\search_api_db\Event\QueryPreExecuteEvent
   */
  const QUERY_PRE_EXECUTE = 'search_api_db.query_pre_execute';

}

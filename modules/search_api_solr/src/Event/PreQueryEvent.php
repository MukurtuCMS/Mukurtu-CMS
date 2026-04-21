<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event before a Search API query gets finally converted into a solarium query.
 */
final class PreQueryEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

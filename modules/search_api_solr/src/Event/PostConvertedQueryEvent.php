<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event after a Search API query has been converted into a solarium query.
 */
final class PostConvertedQueryEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

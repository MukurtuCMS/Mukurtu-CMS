<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired after facets are extracted from the solarium result.
 */
final class PostSetFacetsEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

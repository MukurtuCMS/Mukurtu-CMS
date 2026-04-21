<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired before facets are set on the solarium query.
 */
final class PreSetFacetsEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

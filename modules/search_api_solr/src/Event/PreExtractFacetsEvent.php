<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired before facets are extracted from the solarium result.
 */
final class PreExtractFacetsEvent extends AbstractSearchApiQuerySolariumResultEvent {}

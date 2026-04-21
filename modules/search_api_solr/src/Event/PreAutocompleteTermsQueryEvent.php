<?php

namespace Drupal\search_api_solr\Event;

/**
 * Event to be fired before the autocomplete terms solarium query gets executed.
 */
final class PreAutocompleteTermsQueryEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

<?php

namespace Drupal\search_api_solr_autocomplete\Event;

use Drupal\search_api_solr\Event\AbstractSearchApiQuerySolariumQueryEvent;

/**
 * Event to be fired before the suggester solarium query gets executed.
 */
final class PreSuggesterQueryEvent extends AbstractSearchApiQuerySolariumQueryEvent {}

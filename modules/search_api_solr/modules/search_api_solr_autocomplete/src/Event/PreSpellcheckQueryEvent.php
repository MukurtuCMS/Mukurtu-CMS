<?php

namespace Drupal\search_api_solr_autocomplete\Event;

use Drupal\search_api_solr\Event\AbstractSearchApiQuerySolariumQueryEvent;

/**
 * Event to be fired before the spellcheck solarium query gets executed.
 */
final class PreSpellcheckQueryEvent extends AbstractSearchApiQuerySolariumQueryEvent {}
